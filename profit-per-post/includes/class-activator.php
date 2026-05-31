<?php
/**
 * Plugin Activator - runs on plugin activation.
 *
 * @package ProfitPerPost
 */

namespace ProfitPerPost;

use ProfitPerPost\Database\Migrator;
use ProfitPerPost\Security\Encryption;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activator
 *
 * Handles all logic that needs to run when the plugin is activated.
 */
class Activator {

    /**
     * Run activation tasks.
     *
     * @return void
     */
    public static function activate() {
        // Create database tables.
        self::create_tables();

        // Set default options.
        self::set_default_options();

        // Generate encryption key if not exists.
        self::ensure_encryption_key();

        // Schedule cron events.
        self::schedule_cron();

        // Set activation flag for onboarding redirect.
        set_transient( 'ppp_activation_redirect', true, 30 );

        // Store activation time.
        if ( ! get_option( 'ppp_installed_at' ) ) {
            update_option( 'ppp_installed_at', current_time( 'mysql' ) );
        }

        // Update version.
        update_option( 'ppp_version', PPP_VERSION );

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    private static function create_tables() {
        Migrator::run();
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function set_default_options() {
        $defaults = array(
            'ppp_sync_frequency'       => 'every_six_hours',
            'ppp_currency'             => 'USD',
            'ppp_date_format'          => 'M j, Y',
            'ppp_posts_per_page'       => 20,
            'ppp_default_date_range'   => '30',
            'ppp_show_revenue_column'  => true,
            'ppp_debug_mode'           => false,
            'ppp_data_retention_days'  => 365,
            'ppp_affiliate_rev_per_click' => 0.50,
            'ppp_attribution_model'    => 'last_touch',
            'ppp_wc_cookie_days'       => 30,
            'ppp_onboarding_complete'  => false,
            'ppp_enabled_sources'      => array(
                'google_analytics' => false,
                'adsense'          => false,
                'mediavine'        => false,
                'woocommerce'      => false,
                'affiliate'        => true,
            ),
            'ppp_affiliate_patterns'   => array(
                'amazon.com',
                'shareasale.com',
                'awin1.com',
                'impact.com',
                'partnerize.com',
                'cj.com',
            ),
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value );
            }
        }
    }

    /**
     * Ensure an encryption key exists for securing API credentials.
     *
     * @return void
     */
    private static function ensure_encryption_key() {
        if ( ! get_option( PPP_ENCRYPTION_KEY_OPTION ) ) {
            $key = wp_generate_password( 64, true, true );
            update_option( PPP_ENCRYPTION_KEY_OPTION, $key );
        }
    }

    /**
     * Schedule cron events.
     *
     * @return void
     */
    private static function schedule_cron() {
        // Register custom cron schedules.
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

        // Schedule the main sync event.
        if ( ! wp_next_scheduled( 'ppp_sync_all_sources' ) ) {
            $frequency = get_option( 'ppp_sync_frequency', 'every_six_hours' );
            wp_schedule_event( time(), $frequency, 'ppp_sync_all_sources' );
        }

        // Schedule cache cleanup (daily).
        if ( ! wp_next_scheduled( 'ppp_cleanup_cache' ) ) {
            wp_schedule_event( time(), 'daily', 'ppp_cleanup_cache' );
        }

        // Schedule data pruning (weekly).
        if ( ! wp_next_scheduled( 'ppp_prune_data' ) ) {
            wp_schedule_event( time(), 'weekly', 'ppp_prune_data' );
        }
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified schedules.
     */
    public static function add_cron_schedules( $schedules ) {
        $schedules['every_hour'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display'  => __( 'Every Hour', 'profit-per-post' ),
        );

        $schedules['every_six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 6 Hours', 'profit-per-post' ),
        );

        $schedules['every_twelve_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 12 Hours', 'profit-per-post' ),
        );

        return $schedules;
    }
}
