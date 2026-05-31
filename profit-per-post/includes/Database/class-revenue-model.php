<?php
/**
 * Revenue Data Model - CRUD operations for revenue data.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RevenueModel
 *
 * Handles all database operations for the revenue_data table.
 */
class RevenueModel {

    /**
     * Insert or update a revenue record.
     *
     * @param array $data {
     *     @type int    $post_id        The post ID.
     *     @type string $source         Revenue source identifier.
     *     @type float  $revenue_amount Revenue amount.
     *     @type string $currency       Currency code (default 'USD').
     *     @type string $date_recorded  Date in Y-m-d format.
     *     @type string $meta_data      JSON encoded metadata (optional).
     * }
     * @return int|false The record ID or false on failure.
     */
    public static function upsert( $data ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $defaults = array(
            'currency'  => 'USD',
            'meta_data' => null,
        );
        $data = wp_parse_args( $data, $defaults );

        // Check if record exists.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE post_id = %d AND source = %s AND date_recorded = %s",
                $data['post_id'],
                $data['source'],
                $data['date_recorded']
            )
        );

        if ( $existing ) {
            // Update existing record.
            $wpdb->update(
                $table,
                array(
                    'revenue_amount' => $data['revenue_amount'],
                    'currency'       => $data['currency'],
                    'meta_data'      => $data['meta_data'],
                    'updated_at'     => current_time( 'mysql' ),
                ),
                array( 'id' => $existing ),
                array( '%f', '%s', '%s', '%s' ),
                array( '%d' )
            );
            return (int) $existing;
        }

        // Insert new record.
        $result = $wpdb->insert(
            $table,
            array(
                'post_id'        => $data['post_id'],
                'source'         => $data['source'],
                'revenue_amount' => $data['revenue_amount'],
                'currency'       => $data['currency'],
                'date_recorded'  => $data['date_recorded'],
                'meta_data'      => $data['meta_data'],
                'created_at'     => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Bulk upsert revenue records.
     *
     * @param array $records Array of record data arrays.
     * @return int Number of records processed.
     */
    public static function bulk_upsert( $records ) {
        $count = 0;
        foreach ( $records as $record ) {
            $result = self::upsert( $record );
            if ( $result ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get total revenue for a specific post in a date range.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param string $source     Optional specific source filter.
     * @return float Total revenue.
     */
    public static function get_post_revenue( $post_id, $start_date, $end_date, $source = null ) {
        global $wpdb;
        $table = Schema::revenue_table();

        if ( $source ) {
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(revenue_amount), 0) FROM {$table}
                    WHERE post_id = %d AND date_recorded BETWEEN %s AND %s AND source = %s",
                    $post_id,
                    $start_date,
                    $end_date,
                    $source
                )
            );
        } else {
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(revenue_amount), 0) FROM {$table}
                    WHERE post_id = %d AND date_recorded BETWEEN %s AND %s",
                    $post_id,
                    $start_date,
                    $end_date
                )
            );
        }

        return (float) $result;
    }

    /**
     * Get revenue breakdown by source for a post.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Array of source => revenue pairs.
     */
    public static function get_post_revenue_by_source( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source, COALESCE(SUM(revenue_amount), 0) as total
                FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s
                GROUP BY source
                ORDER BY total DESC",
                $post_id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        $breakdown = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $breakdown[ $row['source'] ] = (float) $row['total'];
            }
        }

        return $breakdown;
    }

    /**
     * Get all posts with their total revenue, sorted by revenue.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param int    $limit      Number of results.
     * @param int    $offset     Offset for pagination.
     * @param string $order      'DESC' or 'ASC'.
     * @return array
     */
    public static function get_all_posts_revenue( $start_date, $end_date, $limit = 20, $offset = 0, $order = 'DESC' ) {
        global $wpdb;
        $table = Schema::revenue_table();
        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, COALESCE(SUM(revenue_amount), 0) as total_revenue,
                COUNT(DISTINCT source) as source_count
                FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s
                GROUP BY post_id
                ORDER BY total_revenue {$order}
                LIMIT %d OFFSET %d",
                $start_date,
                $end_date,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Get total revenue across all posts for a date range.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return float
     */
    public static function get_total_revenue( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(revenue_amount), 0) FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return (float) $result;
    }

    /**
     * Get revenue by source across all posts.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function get_revenue_by_source( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT source, COALESCE(SUM(revenue_amount), 0) as total
                FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s
                GROUP BY source
                ORDER BY total DESC",
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        $breakdown = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $breakdown[ $row['source'] ] = (float) $row['total'];
            }
        }

        return $breakdown;
    }

    /**
     * Get daily revenue trend for chart display.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Array of date => revenue pairs.
     */
    public static function get_revenue_trend( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date_recorded, COALESCE(SUM(revenue_amount), 0) as daily_revenue
                FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s
                GROUP BY date_recorded
                ORDER BY date_recorded ASC",
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        $trend = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $trend[ $row['date_recorded'] ] = (float) $row['daily_revenue'];
            }
        }

        return $trend;
    }

    /**
     * Get the count of posts with zero revenue.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_dead_posts_count( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        // Get all published post IDs.
        $all_posts = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'"
        );

        if ( empty( $all_posts ) ) {
            return 0;
        }

        // Get posts that have revenue.
        $posts_with_revenue = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s AND revenue_amount > 0",
                $start_date,
                $end_date
            )
        );

        return count( array_diff( $all_posts, $posts_with_revenue ) );
    }

    /**
     * Get posts with zero revenue (dead posts).
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param int    $limit      Number of results.
     * @param int    $offset     Offset for pagination.
     * @return array Array of post IDs.
     */
    public static function get_dead_posts( $start_date, $end_date, $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table = Schema::revenue_table();

        // Get posts that have revenue.
        $posts_with_revenue = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s AND revenue_amount > 0",
                $start_date,
                $end_date
            )
        );

        $exclude_clause = '';
        if ( ! empty( $posts_with_revenue ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $posts_with_revenue ), '%d' ) );
            $exclude_clause = $wpdb->prepare(
                "AND ID NOT IN ({$placeholders})",
                $posts_with_revenue
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_status = 'publish' AND post_type = 'post' {$exclude_clause}
                ORDER BY post_date DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        return $results ? $results : array();
    }

    /**
     * Get revenue for a post over time (monthly aggregation).
     *
     * @param int $post_id The post ID.
     * @param int $months  Number of months to look back.
     * @return array
     */
    public static function get_post_revenue_history( $post_id, $months = 12 ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $start_date = gmdate( 'Y-m-d', strtotime( "-{$months} months" ) );
        $end_date   = gmdate( 'Y-m-d' );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(date_recorded, '%%Y-%%m') as month,
                COALESCE(SUM(revenue_amount), 0) as monthly_revenue
                FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s
                GROUP BY month
                ORDER BY month ASC",
                $post_id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Delete revenue data for a specific post.
     *
     * @param int $post_id The post ID.
     * @return int Number of rows deleted.
     */
    public static function delete_by_post( $post_id ) {
        global $wpdb;
        $table = Schema::revenue_table();

        return $wpdb->delete(
            $table,
            array( 'post_id' => $post_id ),
            array( '%d' )
        );
    }

    /**
     * Delete old revenue data beyond retention period.
     *
     * @param int $days Number of days to retain.
     * @return int Number of rows deleted.
     */
    public static function prune_old_data( $days = 365 ) {
        global $wpdb;
        $table    = Schema::revenue_table();
        $cutoff   = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE date_recorded < %s",
                $cutoff
            )
        );
    }

    /**
     * Get total count of posts with revenue data.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_posts_with_revenue_count( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::revenue_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return (int) $result;
    }
}
