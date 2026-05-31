<?php
/**
 * WooCommerce Integration.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

use ProfitPerPost\Database\RevenueModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WooCommerce
 *
 * Integrates with WooCommerce to track product sales
 * attributed to specific blog posts via referral cookies.
 */
class WooCommerce extends BaseIntegration {

    /**
     * Cookie name for tracking referral post.
     *
     * @var string
     */
    const COOKIE_NAME = 'ppp_referrer_post_id';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source_id   = 'woocommerce';
        $this->source_name = __( 'WooCommerce', 'profit-per-post' );
        $this->description = __( 'Track product sales attributed to blog posts via referral tracking.', 'profit-per-post' );
        $this->icon        = 'cart';
        $this->is_internal = true;
    }

    /**
     * Connect - auto-detect WooCommerce.
     *
     * @param array $credentials Not required for WooCommerce.
     * @return array Result array.
     */
    public function connect( $credentials ) {
        if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'woocommerce' ) ) {
            return array(
                'success' => false,
                'message' => __( 'WooCommerce is not installed or active.', 'profit-per-post' ),
            );
        }

        $config = array(
            'enabled'          => true,
            'cookie_days'      => isset( $credentials['cookie_days'] ) ? absint( $credentials['cookie_days'] ) : 30,
            'attribution_model' => isset( $credentials['attribution_model'] ) ? sanitize_text_field( $credentials['attribution_model'] ) : 'last_touch',
        );

        $stored = $this->store_connection( $config, 'connected', 'WooCommerce Active' );

        if ( ! $stored ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to save WooCommerce configuration.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'WooCommerce tracking enabled successfully.', 'profit-per-post' ),
        );
    }

    /**
     * Test connection - check if WooCommerce is active.
     *
     * @return array
     */
    public function test_connection() {
        if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'woocommerce' ) ) {
            return array(
                'success' => false,
                'message' => __( 'WooCommerce is not active.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'WooCommerce is active and tracking is enabled.', 'profit-per-post' ),
        );
    }

    /**
     * Sync - WooCommerce data is tracked in real-time via hooks.
     * This method re-processes recent orders if needed.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync( $start_date, $end_date ) {
        if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'woocommerce' ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'WooCommerce is not active.', 'profit-per-post' ),
            );
        }

        // Re-process orders that have our meta for the given date range.
        $records_synced = $this->reprocess_orders( $start_date, $end_date );

        $this->update_last_synced();

        return array(
            'success'        => true,
            'records_synced' => $records_synced,
            'message'        => sprintf(
                /* translators: %d: Number of records synced */
                __( 'Processed %d WooCommerce order records.', 'profit-per-post' ),
                $records_synced
            ),
        );
    }

    /**
     * Initialize frontend tracking.
     * Sets referral cookie and hooks into WooCommerce order completion.
     *
     * @return void
     */
    public function init_frontend_tracking() {
        if ( ! class_exists( 'WooCommerce' ) && ! class_exists( 'woocommerce' ) ) {
            return;
        }

        // Set referral cookie on single post views.
        add_action( 'template_redirect', array( $this, 'set_referral_cookie' ) );

        // Track order completion.
        add_action( 'woocommerce_order_status_completed', array( $this, 'track_completed_order' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'track_completed_order' ) );

        // Store referral info at checkout.
        add_action( 'woocommerce_checkout_order_created', array( $this, 'store_referral_on_order' ) );
    }

    /**
     * Set referral cookie when viewing a single post.
     *
     * @return void
     */
    public function set_referral_cookie() {
        if ( ! is_single() || is_admin() ) {
            return;
        }

        $post = get_post();
        if ( ! $post || 'post' !== $post->post_type ) {
            return;
        }

        $credentials = $this->get_credentials();
        $cookie_days = ( $credentials && isset( $credentials['cookie_days'] ) ) ? $credentials['cookie_days'] : 30;

        $expiry = time() + ( $cookie_days * DAY_IN_SECONDS );

        // Set cookie with post ID (or update existing based on attribution model).
        $attribution = ( $credentials && isset( $credentials['attribution_model'] ) ) ? $credentials['attribution_model'] : 'last_touch';

        if ( 'last_touch' === $attribution ) {
            // Always overwrite with latest post.
            setcookie( self::COOKIE_NAME, $post->ID, $expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        } elseif ( 'first_touch' === $attribution ) {
            // Only set if not already set.
            if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
                setcookie( self::COOKIE_NAME, $post->ID, $expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
        }
    }

    /**
     * Store referral post ID on the order at checkout.
     *
     * @param \WC_Order $order The WooCommerce order.
     * @return void
     */
    public function store_referral_on_order( $order ) {
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $post_id = absint( $_COOKIE[ self::COOKIE_NAME ] );

            if ( $post_id > 0 && get_post_status( $post_id ) ) {
                $order->update_meta_data( '_ppp_referrer_post_id', $post_id );
                $order->save();
            }
        }
    }

    /**
     * Track a completed order and attribute revenue to the referring post.
     *
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public function track_completed_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check if we already tracked this order.
        $already_tracked = $order->get_meta( '_ppp_revenue_tracked' );
        if ( $already_tracked ) {
            return;
        }

        // Get referral post ID.
        $post_id = (int) $order->get_meta( '_ppp_referrer_post_id' );

        if ( ! $post_id ) {
            // Try from cookie as fallback.
            if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
                $post_id = absint( $_COOKIE[ self::COOKIE_NAME ] );
            }
        }

        if ( ! $post_id || ! get_post_status( $post_id ) ) {
            return;
        }

        // Get order total (excluding tax and shipping for net revenue).
        $revenue = (float) $order->get_subtotal();

        if ( $revenue <= 0 ) {
            return;
        }

        $date = $order->get_date_completed()
            ? $order->get_date_completed()->format( 'Y-m-d' )
            : gmdate( 'Y-m-d' );

        // Record the revenue.
        RevenueModel::upsert( array(
            'post_id'        => $post_id,
            'source'         => 'woocommerce',
            'revenue_amount' => $revenue,
            'currency'       => $order->get_currency(),
            'date_recorded'  => $date,
            'meta_data'      => wp_json_encode( array(
                'order_id'     => $order_id,
                'order_status' => $order->get_status(),
            )),
        ));

        // Mark order as tracked.
        $order->update_meta_data( '_ppp_revenue_tracked', '1' );
        $order->save();
    }

    /**
     * Re-process existing orders for a date range.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int Number of records processed.
     */
    private function reprocess_orders( $start_date, $end_date ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return 0;
        }

        $orders = wc_get_orders( array(
            'status'       => array( 'completed', 'processing' ),
            'date_created' => $start_date . '...' . $end_date,
            'meta_key'     => '_ppp_referrer_post_id',
            'meta_compare' => 'EXISTS',
            'limit'        => 1000,
        ));

        $count = 0;

        foreach ( $orders as $order ) {
            $post_id = (int) $order->get_meta( '_ppp_referrer_post_id' );

            if ( ! $post_id || ! get_post_status( $post_id ) ) {
                continue;
            }

            $revenue = (float) $order->get_subtotal();
            if ( $revenue <= 0 ) {
                continue;
            }

            $date = $order->get_date_completed()
                ? $order->get_date_completed()->format( 'Y-m-d' )
                : $order->get_date_created()->format( 'Y-m-d' );

            $result = RevenueModel::upsert( array(
                'post_id'        => $post_id,
                'source'         => 'woocommerce',
                'revenue_amount' => $revenue,
                'currency'       => $order->get_currency(),
                'date_recorded'  => $date,
                'meta_data'      => wp_json_encode( array(
                    'order_id'     => $order->get_id(),
                    'order_status' => $order->get_status(),
                )),
            ));

            if ( $result ) {
                $count++;
            }
        }

        return $count;
    }
}
