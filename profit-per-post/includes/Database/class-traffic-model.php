<?php
/**
 * Traffic Data Model - CRUD operations for traffic data.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TrafficModel
 *
 * Handles all database operations for the traffic_data table.
 */
class TrafficModel {

    /**
     * Insert or update a traffic record.
     *
     * @param array $data {
     *     @type int    $post_id          The post ID.
     *     @type int    $pageviews        Number of pageviews.
     *     @type int    $unique_visitors  Number of unique visitors.
     *     @type float  $avg_time_on_page Average time on page in seconds.
     *     @type float  $bounce_rate      Bounce rate percentage.
     *     @type string $date_recorded    Date in Y-m-d format.
     *     @type string $source           Data source identifier.
     * }
     * @return int|false The record ID or false on failure.
     */
    public static function upsert( $data ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $defaults = array(
            'unique_visitors'  => 0,
            'avg_time_on_page' => 0.00,
            'bounce_rate'      => 0.00,
            'source'           => 'google_analytics',
        );
        $data = wp_parse_args( $data, $defaults );

        // Check if record exists.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE post_id = %d AND date_recorded = %s AND source = %s",
                $data['post_id'],
                $data['date_recorded'],
                $data['source']
            )
        );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'pageviews'        => $data['pageviews'],
                    'unique_visitors'  => $data['unique_visitors'],
                    'avg_time_on_page' => $data['avg_time_on_page'],
                    'bounce_rate'      => $data['bounce_rate'],
                    'updated_at'       => current_time( 'mysql' ),
                ),
                array( 'id' => $existing ),
                array( '%d', '%d', '%f', '%f', '%s' ),
                array( '%d' )
            );
            return (int) $existing;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'post_id'          => $data['post_id'],
                'pageviews'        => $data['pageviews'],
                'unique_visitors'  => $data['unique_visitors'],
                'avg_time_on_page' => $data['avg_time_on_page'],
                'bounce_rate'      => $data['bounce_rate'],
                'date_recorded'    => $data['date_recorded'],
                'source'           => $data['source'],
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Bulk upsert traffic records.
     *
     * @param array $records Array of record data arrays.
     * @return int Number of records processed.
     */
    public static function bulk_upsert( $records ) {
        $count = 0;
        foreach ( $records as $record ) {
            if ( self::upsert( $record ) ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get total pageviews for a post in a date range.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_post_pageviews( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(pageviews), 0) FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s",
                $post_id,
                $start_date,
                $end_date
            )
        );

        return (int) $result;
    }

    /**
     * Get traffic data for a post in a date range.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function get_post_traffic( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(pageviews), 0) as total_pageviews,
                    COALESCE(SUM(unique_visitors), 0) as total_visitors,
                    COALESCE(AVG(avg_time_on_page), 0) as avg_time,
                    COALESCE(AVG(bounce_rate), 0) as avg_bounce_rate
                FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s",
                $post_id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return $result ? $result : array(
            'total_pageviews'  => 0,
            'total_visitors'   => 0,
            'avg_time'         => 0,
            'avg_bounce_rate'  => 0,
        );
    }

    /**
     * Get traffic for all posts in a date range.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param int    $limit      Number of results.
     * @param int    $offset     Offset for pagination.
     * @return array
     */
    public static function get_all_posts_traffic( $start_date, $end_date, $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, COALESCE(SUM(pageviews), 0) as total_pageviews,
                COALESCE(SUM(unique_visitors), 0) as total_visitors
                FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s
                GROUP BY post_id
                ORDER BY total_pageviews DESC
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
     * Get daily traffic trend.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function get_traffic_trend( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date_recorded, COALESCE(SUM(pageviews), 0) as daily_pageviews
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
                $trend[ $row['date_recorded'] ] = (int) $row['daily_pageviews'];
            }
        }

        return $trend;
    }

    /**
     * Get total pageviews across all posts.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_total_pageviews( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::traffic_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(pageviews), 0) FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return (int) $result;
    }

    /**
     * Delete traffic data for a specific post.
     *
     * @param int $post_id The post ID.
     * @return int Number of rows deleted.
     */
    public static function delete_by_post( $post_id ) {
        global $wpdb;
        $table = Schema::traffic_table();

        return $wpdb->delete(
            $table,
            array( 'post_id' => $post_id ),
            array( '%d' )
        );
    }

    /**
     * Delete old traffic data beyond retention period.
     *
     * @param int $days Number of days to retain.
     * @return int Number of rows deleted.
     */
    public static function prune_old_data( $days = 365 ) {
        global $wpdb;
        $table  = Schema::traffic_table();
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE date_recorded < %s",
                $cutoff
            )
        );
    }
}
