<?php
/**
 * Transient Cache - WordPress transient wrapper.
 *
 * @package ProfitPerPost\Cache
 */

namespace ProfitPerPost\Cache;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TransientCache
 *
 * Convenience wrapper around WordPress transients
 * with namespacing and batch operations.
 */
class TransientCache {

    /**
     * Prefix for all transients.
     *
     * @var string
     */
    const PREFIX = 'ppp_';

    /**
     * Get a transient value.
     *
     * @param string $key The transient key (without prefix).
     * @return mixed|false The transient value or false.
     */
    public static function get( $key ) {
        return get_transient( self::PREFIX . $key );
    }

    /**
     * Set a transient value.
     *
     * @param string $key        The transient key (without prefix).
     * @param mixed  $value      The value to store.
     * @param int    $expiration Time until expiration in seconds.
     * @return bool
     */
    public static function set( $key, $value, $expiration = 3600 ) {
        return set_transient( self::PREFIX . $key, $value, $expiration );
    }

    /**
     * Delete a transient.
     *
     * @param string $key The transient key (without prefix).
     * @return bool
     */
    public static function delete( $key ) {
        return delete_transient( self::PREFIX . $key );
    }

    /**
     * Get or set: retrieve from cache, or execute callback and cache result.
     *
     * @param string   $key        The cache key.
     * @param callable $callback   Callback to generate value if not cached.
     * @param int      $expiration Cache expiration in seconds.
     * @return mixed The cached or freshly computed value.
     */
    public static function remember( $key, $callback, $expiration = 3600 ) {
        $cached = self::get( $key );

        if ( false !== $cached ) {
            return $cached;
        }

        $value = call_user_func( $callback );

        if ( false !== $value && null !== $value ) {
            self::set( $key, $value, $expiration );
        }

        return $value;
    }

    /**
     * Delete multiple transients matching a pattern.
     *
     * @param string $pattern Pattern to match (SQL LIKE syntax).
     * @return int Number of transients deleted.
     */
    public static function delete_by_pattern( $pattern ) {
        global $wpdb;

        $full_pattern = '_transient_' . self::PREFIX . $pattern;

        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                $full_pattern,
                '_transient_timeout_' . self::PREFIX . $pattern
            )
        );

        return (int) $count;
    }

    /**
     * Check if a transient exists and is not expired.
     *
     * @param string $key The transient key.
     * @return bool
     */
    public static function exists( $key ) {
        return false !== self::get( $key );
    }

    /**
     * Increment a numeric transient value.
     *
     * @param string $key        The transient key.
     * @param int    $amount     Amount to increment by.
     * @param int    $expiration Expiration if creating new.
     * @return int The new value.
     */
    public static function increment( $key, $amount = 1, $expiration = 3600 ) {
        $current = self::get( $key );
        $new_val = ( false !== $current ) ? ( (int) $current + $amount ) : $amount;
        self::set( $key, $new_val, $expiration );
        return $new_val;
    }
}
