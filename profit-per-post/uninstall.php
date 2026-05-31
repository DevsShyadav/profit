<?php
/**
 * Uninstall handler - completely removes all plugin data.
 *
 * Fired when the plugin is deleted (not just deactivated).
 * This removes all database tables, options, transients, and files.
 *
 * @package ProfitPerPost
 */

// Exit if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load plugin constants.
define( 'PPP_TABLE_PREFIX', 'ppp_' );

global $wpdb;

// 1. Drop all custom tables.
$tables = array(
    $wpdb->prefix . PPP_TABLE_PREFIX . 'revenue_data',
    $wpdb->prefix . PPP_TABLE_PREFIX . 'traffic_data',
    $wpdb->prefix . PPP_TABLE_PREFIX . 'affiliate_clicks',
    $wpdb->prefix . PPP_TABLE_PREFIX . 'sync_log',
    $wpdb->prefix . PPP_TABLE_PREFIX . 'connections',
    $wpdb->prefix . PPP_TABLE_PREFIX . 'cache',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
}

// 2. Delete all plugin options.
$options = array(
    'ppp_version',
    'ppp_db_version',
    'ppp_installed_at',
    'ppp_encryption_key',
    'ppp_sync_frequency',
    'ppp_currency',
    'ppp_date_format',
    'ppp_posts_per_page',
    'ppp_default_date_range',
    'ppp_show_revenue_column',
    'ppp_debug_mode',
    'ppp_data_retention_days',
    'ppp_affiliate_rev_per_click',
    'ppp_attribution_model',
    'ppp_wc_cookie_days',
    'ppp_onboarding_complete',
    'ppp_enabled_sources',
    'ppp_affiliate_patterns',
    'ppp_dismissed_notices',
    'ppp_sync_queue',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// 3. Delete all transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_ppp_%'
    OR option_name LIKE '_transient_timeout_ppp_%'"
);

// 4. Clear scheduled cron events.
$cron_events = array(
    'ppp_sync_all_sources',
    'ppp_cleanup_cache',
    'ppp_prune_data',
    'ppp_sync_single_source',
);

foreach ( $cron_events as $event ) {
    wp_clear_scheduled_hook( $event );
}

// 5. Remove custom capabilities from roles.
$capabilities = array(
    'ppp_view_revenue',
    'ppp_manage_settings',
    'ppp_manage_connections',
    'ppp_export_data',
    'ppp_trigger_sync',
);

$roles = array( 'administrator', 'editor', 'author' );

foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( $role ) {
        foreach ( $capabilities as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}

// 6. Delete log files.
$upload_dir = wp_upload_dir();
$log_dir    = $upload_dir['basedir'] . '/profit-per-post-logs';

if ( is_dir( $log_dir ) ) {
    $files = glob( $log_dir . '/*' );
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
    }
    rmdir( $log_dir );
}

// 7. Delete post meta added by WooCommerce integration.
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
    WHERE meta_key IN ('_ppp_referrer_post_id', '_ppp_revenue_tracked')"
);
