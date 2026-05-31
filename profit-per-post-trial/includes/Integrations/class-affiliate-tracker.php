<?php
/**
 * Affiliate Link Tracker Integration.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

use ProfitPerPost\Database\AffiliateModel;
use ProfitPerPost\Database\RevenueModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AffiliateTracker
 *
 * Built-in affiliate link click tracking system.
 * Monitors outbound link clicks from posts and records them
 * with estimated revenue per click.
 */
class AffiliateTracker extends BaseIntegration {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source_id   = 'affiliate';
        $this->source_name = __( 'Affiliate Links', 'profit-per-post' );
        $this->description = __( 'Track outbound affiliate link clicks and estimate revenue per post.', 'profit-per-post' );
        $this->icon        = 'admin-links';
        $this->is_internal = true;
    }

    /**
     * Connect - enable affiliate tracking.
     *
     * @param array $credentials {
     *     @type float $revenue_per_click Estimated revenue per click.
     *     @type array $patterns          URL patterns to track.
     * }
     * @return array Result array.
     */
    public function connect( $credentials ) {
        $config = array(
            'enabled'           => true,
            'revenue_per_click' => isset( $credentials['revenue_per_click'] ) ? floatval( $credentials['revenue_per_click'] ) : 0.50,
            'patterns'          => isset( $credentials['patterns'] ) ? $this->sanitize_patterns( $credentials['patterns'] ) : $this->get_default_patterns(),
        );

        $stored = $this->store_connection( $config, 'connected', count( $config['patterns'] ) . ' patterns' );

        if ( ! $stored ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to save affiliate configuration.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Affiliate link tracking enabled.', 'profit-per-post' ),
        );
    }

    /**
     * Test connection - always works since it's internal.
     *
     * @return array
     */
    public function test_connection() {
        return array(
            'success' => true,
            'message' => __( 'Affiliate tracking is active.', 'profit-per-post' ),
        );
    }

    /**
     * Sync - calculate estimated revenue from tracked clicks.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync( $start_date, $end_date ) {
        $credentials = $this->get_credentials();
        $rev_per_click = ( $credentials && isset( $credentials['revenue_per_click'] ) )
            ? (float) $credentials['revenue_per_click']
            : (float) get_option( 'ppp_affiliate_rev_per_click', 0.50 );

        // Get all affiliate clicks grouped by post and date.
        global $wpdb;
        $table = \ProfitPerPost\Database\Schema::affiliate_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, date_recorded, SUM(click_count) as total_clicks
                FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s
                GROUP BY post_id, date_recorded",
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        if ( empty( $results ) ) {
            $this->update_last_synced();
            return array(
                'success'        => true,
                'records_synced' => 0,
                'message'        => __( 'No affiliate clicks to process.', 'profit-per-post' ),
            );
        }

        $records = array();
        foreach ( $results as $row ) {
            $estimated_revenue = (int) $row['total_clicks'] * $rev_per_click;

            if ( $estimated_revenue > 0 ) {
                $records[] = array(
                    'post_id'        => (int) $row['post_id'],
                    'source'         => 'affiliate',
                    'revenue_amount' => $estimated_revenue,
                    'currency'       => get_option( 'ppp_currency', 'USD' ),
                    'date_recorded'  => $row['date_recorded'],
                    'meta_data'      => wp_json_encode( array(
                        'clicks'          => (int) $row['total_clicks'],
                        'rev_per_click'   => $rev_per_click,
                    )),
                );
            }
        }

        $records_synced = RevenueModel::bulk_upsert( $records );
        $this->update_last_synced();

        return array(
            'success'        => true,
            'records_synced' => $records_synced,
            'message'        => sprintf(
                /* translators: %d: Number of records synced */
                __( 'Calculated revenue for %d affiliate click records.', 'profit-per-post' ),
                $records_synced
            ),
        );
    }

    /**
     * Initialize frontend tracking.
     *
     * @return void
     */
    public function init_frontend_tracking() {
        if ( ! $this->is_connected() ) {
            return;
        }

        // Enqueue tracking script on single posts.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

        // Register REST endpoint for click tracking.
        add_action( 'rest_api_init', array( $this, 'register_tracking_endpoint' ) );
    }

    /**
     * Enqueue the affiliate click tracking script.
     *
     * @return void
     */
    public function enqueue_tracking_script() {
        if ( ! is_single() ) {
            return;
        }

        $post = get_post();
        if ( ! $post || 'post' !== $post->post_type ) {
            return;
        }

        $credentials = $this->get_credentials();
        $patterns    = ( $credentials && isset( $credentials['patterns'] ) ) ? $credentials['patterns'] : $this->get_default_patterns();

        wp_enqueue_script(
            'ppp-affiliate-tracker',
            PPP_PLUGIN_URL . 'assets/dist/js/affiliate-tracker.js',
            array(),
            PPP_VERSION,
            true
        );

        wp_localize_script( 'ppp-affiliate-tracker', 'pppAffiliateTracker', array(
            'restUrl'  => rest_url( PPP_REST_NAMESPACE . '/track-click' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'postId'   => $post->ID,
            'patterns' => $patterns,
        ));
    }

    /**
     * Register the click tracking REST endpoint.
     *
     * @return void
     */
    public function register_tracking_endpoint() {
        register_rest_route( PPP_REST_NAMESPACE, '/track-click', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_track_click' ),
            'permission_callback' => '__return_true', // Public endpoint.
            'args'                => array(
                'post_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'link_url' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'link_label' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Handle the click tracking REST request.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function handle_track_click( $request ) {
        $post_id    = $request->get_param( 'post_id' );
        $link_url   = $request->get_param( 'link_url' );
        $link_label = $request->get_param( 'link_label' );

        // Validate post exists.
        if ( ! get_post_status( $post_id ) ) {
            return new \WP_REST_Response( array( 'success' => false ), 400 );
        }

        // Validate URL matches tracked patterns.
        if ( ! $this->url_matches_patterns( $link_url ) ) {
            return new \WP_REST_Response( array( 'success' => false ), 400 );
        }

        // Get estimated revenue per click.
        $credentials     = $this->get_credentials();
        $rev_per_click   = ( $credentials && isset( $credentials['revenue_per_click'] ) )
            ? (float) $credentials['revenue_per_click']
            : 0.50;

        // Record the click.
        $result = AffiliateModel::record_click( array(
            'post_id'           => $post_id,
            'link_url'          => $link_url,
            'link_label'        => $link_label,
            'estimated_revenue' => $rev_per_click,
            'date_recorded'     => gmdate( 'Y-m-d' ),
        ));

        return new \WP_REST_Response( array( 'success' => (bool) $result ), 200 );
    }

    /**
     * Check if a URL matches configured affiliate patterns.
     *
     * @param string $url The URL to check.
     * @return bool
     */
    public function url_matches_patterns( $url ) {
        $credentials = $this->get_credentials();
        $patterns    = ( $credentials && isset( $credentials['patterns'] ) ) ? $credentials['patterns'] : $this->get_default_patterns();

        $url_lower = strtolower( $url );

        foreach ( $patterns as $pattern ) {
            if ( false !== strpos( $url_lower, strtolower( $pattern ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get default affiliate URL patterns.
     *
     * @return array
     */
    private function get_default_patterns() {
        return get_option( 'ppp_affiliate_patterns', array(
            'amazon.com',
            'amzn.to',
            'shareasale.com',
            'awin1.com',
            'impact.com',
            'partnerize.com',
            'cj.com',
            'commission-junction.com',
            'rakuten.com',
            'clickbank.net',
            'jvzoo.com',
            'hotmart.com',
        ));
    }

    /**
     * Sanitize URL patterns array.
     *
     * @param array $patterns The patterns to sanitize.
     * @return array
     */
    private function sanitize_patterns( $patterns ) {
        if ( ! is_array( $patterns ) ) {
            $patterns = explode( "\n", $patterns );
        }

        $sanitized = array();

        foreach ( $patterns as $pattern ) {
            $pattern = trim( sanitize_text_field( $pattern ) );
            if ( ! empty( $pattern ) ) {
                $sanitized[] = $pattern;
            }
        }

        return array_unique( $sanitized );
    }

    /**
     * Get configured patterns.
     *
     * @return array
     */
    public function get_patterns() {
        $credentials = $this->get_credentials();
        return ( $credentials && isset( $credentials['patterns'] ) ) ? $credentials['patterns'] : $this->get_default_patterns();
    }

    /**
     * Update affiliate patterns.
     *
     * @param array $patterns New patterns.
     * @return bool
     */
    public function update_patterns( $patterns ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials ) {
            $credentials = array(
                'enabled'           => true,
                'revenue_per_click' => 0.50,
            );
        }

        $credentials['patterns'] = $this->sanitize_patterns( $patterns );

        return $this->store_connection( $credentials, 'connected', count( $credentials['patterns'] ) . ' patterns' );
    }

    /**
     * Update revenue per click setting.
     *
     * @param float $amount Revenue per click amount.
     * @return bool
     */
    public function update_revenue_per_click( $amount ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials ) {
            $credentials = array(
                'enabled'  => true,
                'patterns' => $this->get_default_patterns(),
            );
        }

        $credentials['revenue_per_click'] = max( 0, floatval( $amount ) );

        return $this->store_connection( $credentials, 'connected' );
    }
}
