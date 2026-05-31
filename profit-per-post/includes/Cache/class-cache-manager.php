<?php
/**
 * Cache Manager - orchestrates caching operations.
 *
 * @package ProfitPerPost\Cache
 */

namespace ProfitPerPost\Cache;

use ProfitPerPost\Database\CacheModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CacheManager
 *
 * Provides a unified caching interface combining
 * database-level cache (for computed data) and
 * WordPress transients (for short-lived data).
 */
class CacheManager {

    /**
     * Transient prefix.
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'ppp_';

    /**
     * Get cached data by key.
     * Checks transient first, then database cache.
     *
     * @param string $key The cache key.
     * @return mixed|false Cached value or false if not found.
     */
    public function get( $key ) {
        // Try transient first (fastest).
        $transient = get_transient( self::TRANSIENT_PREFIX . $key );
        if ( false !== $transient ) {
            return $transient;
        }

        // Try database cache.
        $db_cached = CacheModel::get( $key );
        if ( false !== $db_cached ) {
            // Restore to transient for faster subsequent access.
            set_transient( self::TRANSIENT_PREFIX . $key, $db_cached, 900 ); // 15 min transient.
            return $db_cached;
        }

        return false;
    }

    /**
     * Set cached data.
     * Stores in both transient (short-lived) and database (longer-lived).
     *
     * @param string $key          The cache key.
     * @param mixed  $value        The value to cache.
     * @param string $period_start Period start date (Y-m-d).
     * @param string $period_end   Period end date (Y-m-d).
     * @param int    $ttl_seconds  Time to live in seconds.
     * @return bool
     */
    public function set( $key, $value, $period_start, $period_end, $ttl_seconds = 3600 ) {
        // Store in transient (short TTL for speed).
        $transient_ttl = min( $ttl_seconds, 900 ); // Max 15 min for transients.
        set_transient( self::TRANSIENT_PREFIX . $key, $value, $transient_ttl );

        // Store in database cache (longer TTL).
        return CacheModel::set( $key, $value, $period_start, $period_end, $ttl_seconds );
    }

    /**
     * Delete a specific cache entry.
     *
     * @param string $key The cache key.
     * @return bool
     */
    public function delete( $key ) {
        delete_transient( self::TRANSIENT_PREFIX . $key );
        return CacheModel::delete( $key );
    }

    /**
     * Invalidate all cache for a date period.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return void
     */
    public function invalidate_period( $start_date, $end_date ) {
        // Clear database cache for overlapping periods.
        CacheModel::invalidate_period( $start_date, $end_date );

        // Clear all dashboard transients.
        $this->clear_dashboard_transients();
    }

    /**
     * Clear all plugin cache.
     *
     * @return void
     */
    public function clear_all() {
        // Clear database cache.
        CacheModel::clear_all();

        // Clear all transients.
        $this->clear_all_transients();
    }

    /**
     * Clear expired cache entries.
     *
     * @return int Number of entries cleared.
     */
    public function cleanup() {
        return CacheModel::clear_expired();
    }

    /**
     * Clear dashboard-related transients.
     *
     * @return void
     */
    private function clear_dashboard_transients() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ppp_dashboard_%'
            OR option_name LIKE '_transient_timeout_ppp_dashboard_%'
            OR option_name LIKE '_transient_ppp_post_rev_%'
            OR option_name LIKE '_transient_timeout_ppp_post_rev_%'"
        );
    }

    /**
     * Clear all plugin transients.
     *
     * @return void
     */
    private function clear_all_transients() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ppp_%'
            OR option_name LIKE '_transient_timeout_ppp_%'"
        );
    }

    /**
     * Get cache statistics.
     *
     * @return array
     */
    public function get_stats() {
        return CacheModel::get_stats();
    }

    /**
     * Generate a cache key from parameters.
     *
     * @param string $prefix Key prefix.
     * @param array  $params Parameters to include in key.
     * @return string
     */
    public static function make_key( $prefix, $params = array() ) {
        $key_parts = array( $prefix );

        foreach ( $params as $param ) {
            $key_parts[] = is_array( $param ) ? md5( wp_json_encode( $param ) ) : (string) $param;
        }

        return implode( '_', $key_parts );
    }
}
