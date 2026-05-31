<?php
/**
 * Affiliate Clicks Model - CRUD operations for affiliate click tracking.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AffiliateModel
 *
 * Handles all database operations for the affiliate_clicks table.
 */
class AffiliateModel {

    /**
     * Record an affiliate click.
     *
     * @param array $data {
     *     @type int    $post_id           The post ID where click originated.
     *     @type string $link_url          The affiliate link URL.
     *     @type string $link_label        Label/anchor text of the link.
     *     @type float  $estimated_revenue Estimated revenue per click.
     *     @type string $date_recorded     Date in Y-m-d format.
     * }
     * @return int|false The record ID or false on failure.
     */
    public static function record_click( $data ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $defaults = array(
            'link_label'        => null,
            'estimated_revenue' => 0.0000,
            'date_recorded'     => gmdate( 'Y-m-d' ),
        );
        $data = wp_parse_args( $data, $defaults );

        // Check if a record for this post + URL + date exists.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, click_count FROM {$table}
                WHERE post_id = %d AND link_url = %s AND date_recorded = %s",
                $data['post_id'],
                $data['link_url'],
                $data['date_recorded']
            ),
            ARRAY_A
        );

        if ( $existing ) {
            // Increment click count.
            $wpdb->update(
                $table,
                array(
                    'click_count' => (int) $existing['click_count'] + 1,
                    'updated_at'  => current_time( 'mysql' ),
                ),
                array( 'id' => $existing['id'] ),
                array( '%d', '%s' ),
                array( '%d' )
            );
            return (int) $existing['id'];
        }

        // Insert new record.
        $result = $wpdb->insert(
            $table,
            array(
                'post_id'           => $data['post_id'],
                'link_url'          => $data['link_url'],
                'link_label'        => $data['link_label'],
                'click_count'       => 1,
                'estimated_revenue' => $data['estimated_revenue'],
                'date_recorded'     => $data['date_recorded'],
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get total affiliate clicks for a post.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_post_clicks( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(click_count), 0) FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s",
                $post_id,
                $start_date,
                $end_date
            )
        );

        return (int) $result;
    }

    /**
     * Get estimated affiliate revenue for a post.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return float
     */
    public static function get_post_estimated_revenue( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(click_count * estimated_revenue), 0) FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s",
                $post_id,
                $start_date,
                $end_date
            )
        );

        return (float) $result;
    }

    /**
     * Get affiliate link breakdown for a post.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function get_post_links_breakdown( $post_id, $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT link_url, link_label,
                SUM(click_count) as total_clicks,
                SUM(click_count * estimated_revenue) as total_revenue
                FROM {$table}
                WHERE post_id = %d AND date_recorded BETWEEN %s AND %s
                GROUP BY link_url, link_label
                ORDER BY total_clicks DESC",
                $post_id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Get total affiliate clicks across all posts.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int
     */
    public static function get_total_clicks( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(click_count), 0) FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return (int) $result;
    }

    /**
     * Get total estimated affiliate revenue.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return float
     */
    public static function get_total_estimated_revenue( $start_date, $end_date ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(click_count * estimated_revenue), 0) FROM {$table}
                WHERE date_recorded BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        return (float) $result;
    }

    /**
     * Update estimated revenue per click for a specific link.
     *
     * @param string $link_url          The affiliate link URL.
     * @param float  $estimated_revenue New estimated revenue per click.
     * @return int Number of rows updated.
     */
    public static function update_link_revenue( $link_url, $estimated_revenue ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        return $wpdb->update(
            $table,
            array( 'estimated_revenue' => $estimated_revenue ),
            array( 'link_url' => $link_url ),
            array( '%f' ),
            array( '%s' )
        );
    }

    /**
     * Delete affiliate data for a specific post.
     *
     * @param int $post_id The post ID.
     * @return int Number of rows deleted.
     */
    public static function delete_by_post( $post_id ) {
        global $wpdb;
        $table = Schema::affiliate_table();

        return $wpdb->delete(
            $table,
            array( 'post_id' => $post_id ),
            array( '%d' )
        );
    }

    /**
     * Delete old affiliate data beyond retention period.
     *
     * @param int $days Number of days to retain.
     * @return int Number of rows deleted.
     */
    public static function prune_old_data( $days = 365 ) {
        global $wpdb;
        $table  = Schema::affiliate_table();
        $cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE date_recorded < %s",
                $cutoff
            )
        );
    }
}
