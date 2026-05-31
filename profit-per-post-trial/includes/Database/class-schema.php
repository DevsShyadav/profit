<?php
/**
 * Database Schema definitions.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Schema
 *
 * Defines all database table schemas for the plugin.
 */
class Schema {

    /**
     * Get the full table name with WordPress prefix.
     *
     * @param string $table_name The table name without prefix.
     * @return string Full table name.
     */
    public static function get_table_name( $table_name ) {
        global $wpdb;
        return $wpdb->prefix . PPP_TABLE_PREFIX . $table_name;
    }

    /**
     * Get revenue data table name.
     *
     * @return string
     */
    public static function revenue_table() {
        return self::get_table_name( 'revenue_data' );
    }

    /**
     * Get traffic data table name.
     *
     * @return string
     */
    public static function traffic_table() {
        return self::get_table_name( 'traffic_data' );
    }

    /**
     * Get affiliate clicks table name.
     *
     * @return string
     */
    public static function affiliate_table() {
        return self::get_table_name( 'affiliate_clicks' );
    }

    /**
     * Get sync log table name.
     *
     * @return string
     */
    public static function sync_log_table() {
        return self::get_table_name( 'sync_log' );
    }

    /**
     * Get connections table name.
     *
     * @return string
     */
    public static function connections_table() {
        return self::get_table_name( 'connections' );
    }

    /**
     * Get cache table name.
     *
     * @return string
     */
    public static function cache_table() {
        return self::get_table_name( 'cache' );
    }

    /**
     * Get all table creation SQL statements.
     *
     * @return array Array of SQL CREATE TABLE statements.
     */
    public static function get_all_schemas() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $schemas = array();

        // Revenue data table.
        $table = self::revenue_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            source VARCHAR(50) NOT NULL,
            revenue_amount DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            date_recorded DATE NOT NULL,
            meta_data LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_record (post_id, source, date_recorded),
            KEY idx_post_id (post_id),
            KEY idx_source (source),
            KEY idx_date_recorded (date_recorded),
            KEY idx_post_date (post_id, date_recorded),
            KEY idx_source_date (source, date_recorded)
        ) {$charset_collate};";

        // Traffic data table.
        $table = self::traffic_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            pageviews INT(11) UNSIGNED NOT NULL DEFAULT 0,
            unique_visitors INT(11) UNSIGNED NOT NULL DEFAULT 0,
            avg_time_on_page DECIMAL(8,2) DEFAULT 0.00,
            bounce_rate DECIMAL(5,2) DEFAULT 0.00,
            date_recorded DATE NOT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'google_analytics',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_traffic (post_id, date_recorded, source),
            KEY idx_post_id (post_id),
            KEY idx_date_recorded (date_recorded),
            KEY idx_post_date (post_id, date_recorded)
        ) {$charset_collate};";

        // Affiliate clicks table.
        $table = self::affiliate_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            link_url TEXT NOT NULL,
            link_label VARCHAR(255) DEFAULT NULL,
            click_count INT(11) UNSIGNED NOT NULL DEFAULT 1,
            estimated_revenue DECIMAL(10,4) DEFAULT 0.0000,
            date_recorded DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_date_recorded (date_recorded),
            KEY idx_post_date (post_id, date_recorded)
        ) {$charset_collate};";

        // Sync log table.
        $table = self::sync_log_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            records_synced INT(11) UNSIGNED DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            duration_seconds DECIMAL(8,2) DEFAULT NULL,
            meta_data LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_source (source),
            KEY idx_status (status),
            KEY idx_started_at (started_at)
        ) {$charset_collate};";

        // Connections table.
        $table = self::connections_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'disconnected',
            credentials LONGTEXT NOT NULL,
            account_info TEXT DEFAULT NULL,
            token_expires_at DATETIME DEFAULT NULL,
            last_synced_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_source (source),
            KEY idx_status (status)
        ) {$charset_collate};";

        // Cache table.
        $table = self::cache_table();
        $schemas[] = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cache_key VARCHAR(191) NOT NULL,
            cache_value LONGTEXT NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_cache_key (cache_key),
            KEY idx_expires_at (expires_at),
            KEY idx_period (period_start, period_end)
        ) {$charset_collate};";

        return $schemas;
    }

    /**
     * Get all table names.
     *
     * @return array
     */
    public static function get_all_tables() {
        return array(
            self::revenue_table(),
            self::traffic_table(),
            self::affiliate_table(),
            self::sync_log_table(),
            self::connections_table(),
            self::cache_table(),
        );
    }
}
