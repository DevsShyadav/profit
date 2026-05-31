<?php
/**
 * Plugin Deactivator - runs on plugin deactivation.
 *
 * @package ProfitPerPost
 */

namespace ProfitPerPost;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Deactivator
 *
 * Handles all logic that needs to run when the plugin is deactivated.
 * Note: Does NOT delete data. That happens only on uninstall.
 */
class Deactivator {

    /**
     * Run deactivation tasks.
     *
     * @return void
     */
    public static function deactivate() {
        // Remove scheduled cron events.
        self::remove_cron_events();

        // Clear transient caches.
        self::clear_transients();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Remove all scheduled cron events.
     *
     * @return void
     */
    private static function remove_cron_events() {
        $events = array(
            'ppp_sync_all_sources',
            'ppp_cleanup_cache',
            'ppp_prune_data',
            'ppp_sync_single_source',
        );

        foreach ( $events as $event ) {
            $timestamp = wp_next_scheduled( $event );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $event );
            }
            // Also clear all instances of recurring events.
            wp_clear_scheduled_hook( $event );
        }
    }

    /**
     * Clear plugin transient caches.
     *
     * @return void
     */
    private static function clear_transients() {
        global $wpdb;

        // Delete all transients with our prefix.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ppp_%'
            OR option_name LIKE '_transient_timeout_ppp_%'"
        );

        // Delete site transients for multisite.
        if ( is_multisite() ) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta}
                WHERE meta_key LIKE '_site_transient_ppp_%'
                OR meta_key LIKE '_site_transient_timeout_ppp_%'"
            );
        }
    }
}
