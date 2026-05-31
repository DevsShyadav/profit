<?php
/**
 * Cache Model - CRUD operations for the computed cache table.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CacheModel
 *
 * Handles all database operations for the cache table.
 * Provides persistent caching for pre-computed revenue data.
 */
class CacheModel {

    /**
     * Get a cached value by key.
     *
     * @param string $cache_key The cache key.
     * @return mixed|false The cached value (decoded from JSON) or false if not found/expired.
     */
    public static function get( $cache_key ) {
        global $wpdb;
        $table = Schema::cache_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cache_value FROM {$table}
                WHERE cache_key = %s AND expires_at > NOW()",
                $cache_key
            )
        );

        if ( null === $result ) {
            return false;
        }

        $decoded = json_decode( $result, true );
        return ( null !== $decoded ) ? $decoded : $result;
    }

    /**
     * Set a cache value.
     *
     * @param string $cache_key    The cache key.
     * @param mixed  $value        The value to cache (will be JSON encoded).
     * @param string $period_start Period start date (Y-m-d).
     * @param string $period_end   Period end date (Y-m-d).
     * @param int    $ttl_seconds  Time to live in seconds (default 3600 = 1 hour).
     * @return bool
     */
    public static function set( $cache_key, $value, $period_start, $period_end, $ttl_seconds = 3600 ) {
        global $wpdb;
        $table = Schema::cache_table();

        $cache_value = wp_json_encode( $value );
        $expires_at  = gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds );

        // Delete existing cache for this key.
        $wpdb->delete(
            $table,
            array( 'cache_key' => $cache_key ),
            array( '%s' )
        );

        // Insert new cache entry.
        $result = $wpdb->insert(
            $table,
            array(
                'cache_key'    => $cache_key,
                'cache_value'  => $cache_value,
                'period_start' => $period_start,
                'period_end'   => $period_end,
                'expires_at'   => $expires_at,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return false !== $result;
    }

    /**
     * Delete a specific cache entry.
     *
     * @param string $cache_key The cache key.
     * @return bool
     */
    public static function delete( $cache_key ) {
        global $wpdb;
        $table = Schema::cache_table();

        $result = $wpdb->delete(
            $table,
            array( 'cache_key' => $cache_key ),
            array( '%s' )
        );

        return false !== $result;
    }

    /**
     * Delete all cache entries matching a key pattern.
     *
     * @param string $pattern Key pattern (using SQL LIKE syntax).
     * @return int Number of rows deleted.
     */
    public static function delete_by_pattern( $pattern ) {
        global $wpdb;
        $table = Schema::cache_table();

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE cache_key LIKE %s",
                $pattern
            )
        );
    }

    /**
     * Clear all expired cache entries.
     *
     * @return int Number of rows deleted.
     */
    public static function clear_expired() {
        global $wpdb;
        $table = Schema::cache_table();

        return $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW()"
        );
    }

    /**
     * Clear all cache entries.
     *
     * @return int Number of rows deleted.
     */
    public static function clear_all() {
        global $wpdb;
        $table = Schema::cache_table();

        return $wpdb->query( "TRUNCATE TABLE {$table}" );
    }

    /**
     * Invalidate cache for a specific date range.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int Number of rows deleted.
     */
    public static function invalidate_period( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::cache_table();

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE (period_start <= %s AND period_end >= %s)
                OR (period_start >= %s AND period_start <= %s)
                OR (period_end >= %s AND period_end <= %s)",
                $end_date,
                $start_date,
                $start_date,
                $end_date,
                $start_date,
                $end_date
            )
        );
    }

    /**
     * Get cache statistics.
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = Schema::cache_table();

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $expired = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE expires_at < NOW()" );
        $active = $total - $expired;

        return array(
            'total'   => $total,
            'active'  => $active,
            'expired' => $expired,
        );
    }
}
