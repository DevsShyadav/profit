<?php
/**
 * Revenue Calculator - main calculation engine.
 *
 * @package ProfitPerPost\Revenue
 */

namespace ProfitPerPost\Revenue;

use ProfitPerPost\Database\RevenueModel;
use ProfitPerPost\Database\TrafficModel;
use ProfitPerPost\Database\AffiliateModel;
use ProfitPerPost\Cache\CacheManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RevenueCalculator
 *
 * Main calculation engine that queries all revenue sources,
 * computes totals, aggregates, and provides data for the dashboard.
 */
class RevenueCalculator {

    /**
     * Cache manager instance.
     *
     * @var CacheManager
     */
    private $cache;

    /**
     * Constructor.
     *
     * @param CacheManager $cache Cache manager instance.
     */
    public function __construct( CacheManager $cache ) {
        $this->cache = $cache;
    }

    /**
     * Get complete dashboard data.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Dashboard data array.
     */
    public function get_dashboard_data( $start_date, $end_date ) {
        $cache_key = "dashboard_{$start_date}_{$end_date}";
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = array(
            'totals'        => $this->get_totals( $start_date, $end_date ),
            'top_posts'     => $this->get_top_posts( $start_date, $end_date, 10 ),
            'dead_posts'    => $this->get_dead_posts_summary( $start_date, $end_date ),
            'revenue_trend' => $this->get_revenue_trend( $start_date, $end_date ),
            'by_source'     => $this->get_revenue_by_source( $start_date, $end_date ),
            'by_category'   => $this->get_revenue_by_category( $start_date, $end_date ),
            'period'        => array(
                'start' => $start_date,
                'end'   => $end_date,
            ),
        );

        // Cache for 1 hour.
        $this->cache->set( $cache_key, $data, $start_date, $end_date, 3600 );

        return $data;
    }

    /**
     * Get total revenue and traffic summary.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_totals( $start_date, $end_date ) {
        $total_revenue    = RevenueModel::get_total_revenue( $start_date, $end_date );
        $total_pageviews  = TrafficModel::get_total_pageviews( $start_date, $end_date );
        $posts_with_rev   = RevenueModel::get_posts_with_revenue_count( $start_date, $end_date );
        $dead_posts_count = RevenueModel::get_dead_posts_count( $start_date, $end_date );

        // Get top post revenue.
        $top_posts  = RevenueModel::get_all_posts_revenue( $start_date, $end_date, 1, 0, 'DESC' );
        $top_post_revenue = ! empty( $top_posts ) ? (float) $top_posts[0]['total_revenue'] : 0;

        // Calculate what % of revenue comes from top 10.
        $top_10         = RevenueModel::get_all_posts_revenue( $start_date, $end_date, 10, 0, 'DESC' );
        $top_10_revenue = 0;
        foreach ( $top_10 as $post ) {
            $top_10_revenue += (float) $post['total_revenue'];
        }
        $top_10_percent = $total_revenue > 0 ? round( ( $top_10_revenue / $total_revenue ) * 100 ) : 0;

        // RPM calculation (revenue per mille/1000 pageviews).
        $rpm = $total_pageviews > 0 ? ( $total_revenue / $total_pageviews ) * 1000 : 0;

        // Get previous period for comparison.
        $period_days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;
        $prev_start  = gmdate( 'Y-m-d', strtotime( $start_date ) - ( $period_days * DAY_IN_SECONDS ) );
        $prev_end    = gmdate( 'Y-m-d', strtotime( $start_date ) - DAY_IN_SECONDS );

        $prev_revenue   = RevenueModel::get_total_revenue( $prev_start, $prev_end );
        $prev_pageviews = TrafficModel::get_total_pageviews( $prev_start, $prev_end );

        $revenue_change   = $prev_revenue > 0 ? round( ( ( $total_revenue - $prev_revenue ) / $prev_revenue ) * 100, 1 ) : 0;
        $pageviews_change = $prev_pageviews > 0 ? round( ( ( $total_pageviews - $prev_pageviews ) / $prev_pageviews ) * 100, 1 ) : 0;

        return array(
            'total_revenue'    => round( $total_revenue, 2 ),
            'total_pageviews'  => $total_pageviews,
            'top_post_revenue' => round( $top_post_revenue, 2 ),
            'dead_posts_count' => $dead_posts_count,
            'posts_with_revenue' => $posts_with_rev,
            'top_10_percent'   => $top_10_percent,
            'rpm'              => round( $rpm, 2 ),
            'revenue_change'   => $revenue_change,
            'pageviews_change' => $pageviews_change,
            'currency'         => get_option( 'ppp_currency', 'USD' ),
        );
    }

    /**
     * Get top performing posts.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param int    $limit      Number of posts.
     * @return array
     */
    public function get_top_posts( $start_date, $end_date, $limit = 10 ) {
        $posts = RevenueModel::get_all_posts_revenue( $start_date, $end_date, $limit, 0, 'DESC' );

        $result = array();
        foreach ( $posts as $post_data ) {
            $post_id = (int) $post_data['post_id'];
            $post    = get_post( $post_id );

            if ( ! $post ) {
                continue;
            }

            $traffic = TrafficModel::get_post_traffic( $post_id, $start_date, $end_date );

            $result[] = array(
                'post_id'       => $post_id,
                'title'         => $post->post_title,
                'url'           => get_permalink( $post_id ),
                'revenue'       => round( (float) $post_data['total_revenue'], 2 ),
                'pageviews'     => (int) $traffic['total_pageviews'],
                'source_count'  => (int) $post_data['source_count'],
                'rpm'           => $traffic['total_pageviews'] > 0
                    ? round( ( (float) $post_data['total_revenue'] / (int) $traffic['total_pageviews'] ) * 1000, 2 )
                    : 0,
            );
        }

        return $result;
    }

    /**
     * Get dead posts summary.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_dead_posts_summary( $start_date, $end_date ) {
        $dead_count    = RevenueModel::get_dead_posts_count( $start_date, $end_date );
        $dead_post_ids = RevenueModel::get_dead_posts( $start_date, $end_date, 5, 0 );

        $sample_posts = array();
        foreach ( $dead_post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $traffic = TrafficModel::get_post_pageviews( $post_id, $start_date, $end_date );
                $sample_posts[] = array(
                    'post_id'    => $post_id,
                    'title'      => $post->post_title,
                    'url'        => get_permalink( $post_id ),
                    'pageviews'  => $traffic,
                    'published'  => $post->post_date,
                );
            }
        }

        return array(
            'count'        => $dead_count,
            'sample_posts' => $sample_posts,
        );
    }

    /**
     * Get revenue trend data for charting.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_revenue_trend( $start_date, $end_date ) {
        $revenue_data = RevenueModel::get_revenue_trend( $start_date, $end_date );
        $traffic_data = TrafficModel::get_traffic_trend( $start_date, $end_date );

        // Fill in missing dates with zero.
        $trend = array();
        $current = strtotime( $start_date );
        $end     = strtotime( $end_date );

        while ( $current <= $end ) {
            $date = gmdate( 'Y-m-d', $current );
            $trend[] = array(
                'date'      => $date,
                'revenue'   => isset( $revenue_data[ $date ] ) ? round( $revenue_data[ $date ], 2 ) : 0,
                'pageviews' => isset( $traffic_data[ $date ] ) ? $traffic_data[ $date ] : 0,
            );
            $current += DAY_IN_SECONDS;
        }

        return $trend;
    }

    /**
     * Get revenue breakdown by source.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_revenue_by_source( $start_date, $end_date ) {
        $by_source     = RevenueModel::get_revenue_by_source( $start_date, $end_date );
        $total_revenue = RevenueModel::get_total_revenue( $start_date, $end_date );

        $result = array();
        $source_labels = array(
            'adsense'      => __( 'Google AdSense', 'profit-per-post' ),
            'mediavine'    => __( 'Mediavine', 'profit-per-post' ),
            'woocommerce'  => __( 'WooCommerce', 'profit-per-post' ),
            'affiliate'    => __( 'Affiliate Links', 'profit-per-post' ),
        );

        foreach ( $by_source as $source => $revenue ) {
            $result[] = array(
                'source'     => $source,
                'label'      => isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : ucfirst( $source ),
                'revenue'    => round( $revenue, 2 ),
                'percentage' => $total_revenue > 0 ? round( ( $revenue / $total_revenue ) * 100, 1 ) : 0,
            );
        }

        return $result;
    }

    /**
     * Get revenue breakdown by post category.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_revenue_by_category( $start_date, $end_date ) {
        global $wpdb;
        $revenue_table = \ProfitPerPost\Database\Schema::revenue_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug,
                COALESCE(SUM(r.revenue_amount), 0) as total_revenue,
                COUNT(DISTINCT r.post_id) as post_count
                FROM {$revenue_table} r
                INNER JOIN {$wpdb->term_relationships} tr ON r.post_id = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = 'category'
                AND r.date_recorded BETWEEN %s AND %s
                GROUP BY t.term_id, t.name, t.slug
                ORDER BY total_revenue DESC
                LIMIT 20",
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        $categories = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $categories[] = array(
                    'category_id' => (int) $row['term_id'],
                    'name'        => $row['name'],
                    'slug'        => $row['slug'],
                    'revenue'     => round( (float) $row['total_revenue'], 2 ),
                    'post_count'  => (int) $row['post_count'],
                );
            }
        }

        return $categories;
    }

    /**
     * Get detailed revenue data for a single post.
     *
     * @param int    $post_id    The post ID.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public function get_post_detail( $post_id, $start_date, $end_date ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return array();
        }

        $revenue_total    = RevenueModel::get_post_revenue( $post_id, $start_date, $end_date );
        $revenue_by_source = RevenueModel::get_post_revenue_by_source( $post_id, $start_date, $end_date );
        $traffic           = TrafficModel::get_post_traffic( $post_id, $start_date, $end_date );
        $affiliate_clicks  = AffiliateModel::get_post_clicks( $post_id, $start_date, $end_date );
        $revenue_history   = RevenueModel::get_post_revenue_history( $post_id, 12 );
        $affiliate_links   = AffiliateModel::get_post_links_breakdown( $post_id, $start_date, $end_date );

        // Calculate RPM.
        $pageviews = (int) $traffic['total_pageviews'];
        $rpm       = $pageviews > 0 ? ( $revenue_total / $pageviews ) * 1000 : 0;

        // Previous period comparison.
        $period_days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;
        $prev_start  = gmdate( 'Y-m-d', strtotime( $start_date ) - ( $period_days * DAY_IN_SECONDS ) );
        $prev_end    = gmdate( 'Y-m-d', strtotime( $start_date ) - DAY_IN_SECONDS );

        $prev_revenue = RevenueModel::get_post_revenue( $post_id, $prev_start, $prev_end );
        $revenue_change = $prev_revenue > 0
            ? round( ( ( $revenue_total - $prev_revenue ) / $prev_revenue ) * 100, 1 )
            : 0;

        // Source labels.
        $source_labels = array(
            'adsense'      => __( 'Google AdSense', 'profit-per-post' ),
            'mediavine'    => __( 'Mediavine', 'profit-per-post' ),
            'woocommerce'  => __( 'WooCommerce', 'profit-per-post' ),
            'affiliate'    => __( 'Affiliate Links', 'profit-per-post' ),
        );

        $sources_breakdown = array();
        foreach ( $revenue_by_source as $source => $amount ) {
            $sources_breakdown[] = array(
                'source'  => $source,
                'label'   => isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : ucfirst( $source ),
                'revenue' => round( $amount, 2 ),
            );
        }

        return array(
            'post_id'          => $post_id,
            'title'            => $post->post_title,
            'url'              => get_permalink( $post_id ),
            'published_date'   => $post->post_date,
            'author'           => get_the_author_meta( 'display_name', $post->post_author ),
            'categories'       => wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ),
            'revenue_total'    => round( $revenue_total, 2 ),
            'revenue_change'   => $revenue_change,
            'revenue_by_source' => $sources_breakdown,
            'traffic'          => array(
                'pageviews'      => $pageviews,
                'visitors'       => (int) $traffic['total_visitors'],
                'avg_time'       => round( (float) $traffic['avg_time'], 1 ),
                'bounce_rate'    => round( (float) $traffic['avg_bounce_rate'], 1 ),
            ),
            'rpm'              => round( $rpm, 2 ),
            'affiliate_clicks' => $affiliate_clicks,
            'affiliate_links'  => $affiliate_links,
            'revenue_history'  => $revenue_history,
            'currency'         => get_option( 'ppp_currency', 'USD' ),
        );
    }

    /**
     * Get paginated posts list with revenue data.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @param int    $page       Page number.
     * @param int    $per_page   Items per page.
     * @param string $order_by   Sort field.
     * @param string $order      Sort direction.
     * @param array  $filters    Optional filters (category, source, search).
     * @return array
     */
    public function get_posts_list( $start_date, $end_date, $page = 1, $per_page = 20, $order_by = 'revenue', $order = 'DESC', $filters = array() ) {
        global $wpdb;
        $revenue_table = \ProfitPerPost\Database\Schema::revenue_table();
        $traffic_table = \ProfitPerPost\Database\Schema::traffic_table();

        $offset = ( $page - 1 ) * $per_page;

        // Build the query with joins.
        $where_clauses = array( "p.post_status = 'publish'", "p.post_type = 'post'" );
        $where_params  = array();

        // Search filter.
        if ( ! empty( $filters['search'] ) ) {
            $where_clauses[] = "p.post_title LIKE %s";
            $where_params[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
        }

        // Category filter.
        if ( ! empty( $filters['category'] ) ) {
            $where_clauses[] = "p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.term_id = %d AND tt.taxonomy = 'category'
            )";
            $where_params[] = absint( $filters['category'] );
        }

        // Source filter.
        $source_condition = '';
        if ( ! empty( $filters['source'] ) ) {
            $source_condition = $wpdb->prepare( " AND r.source = %s", $filters['source'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Get total count.
        $count_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p WHERE {$where_sql}";
        if ( ! empty( $where_params ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_params ) );
        } else {
            $total = (int) $wpdb->get_var( $count_query );
        }

        // Main query with revenue and traffic data.
        $order_sql = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

        $order_field = 'total_revenue';
        if ( 'pageviews' === $order_by ) {
            $order_field = 'total_pageviews';
        } elseif ( 'title' === $order_by ) {
            $order_field = 'p.post_title';
        } elseif ( 'date' === $order_by ) {
            $order_field = 'p.post_date';
        }

        $main_query = "SELECT p.ID as post_id, p.post_title, p.post_date,
            COALESCE((SELECT SUM(revenue_amount) FROM {$revenue_table} r WHERE r.post_id = p.ID AND r.date_recorded BETWEEN %s AND %s {$source_condition}), 0) as total_revenue,
            COALESCE((SELECT SUM(pageviews) FROM {$traffic_table} t WHERE t.post_id = p.ID AND t.date_recorded BETWEEN %s AND %s), 0) as total_pageviews
            FROM {$wpdb->posts} p
            WHERE {$where_sql}
            ORDER BY {$order_field} {$order_sql}
            LIMIT %d OFFSET %d";

        $query_params = array_merge(
            array( $start_date, $end_date, $start_date, $end_date ),
            $where_params,
            array( $per_page, $offset )
        );

        $results = $wpdb->get_results(
            $wpdb->prepare( $main_query, $query_params ),
            ARRAY_A
        );

        // Format results.
        $posts = array();
        if ( $results ) {
            foreach ( $results as $row ) {
                $post_id   = (int) $row['post_id'];
                $revenue   = (float) $row['total_revenue'];
                $pageviews = (int) $row['total_pageviews'];

                // Get revenue breakdown by source for each post.
                $by_source = RevenueModel::get_post_revenue_by_source( $post_id, $start_date, $end_date );

                $posts[] = array(
                    'post_id'     => $post_id,
                    'title'       => $row['post_title'],
                    'url'         => get_permalink( $post_id ),
                    'date'        => $row['post_date'],
                    'revenue'     => round( $revenue, 2 ),
                    'pageviews'   => $pageviews,
                    'rpm'         => $pageviews > 0 ? round( ( $revenue / $pageviews ) * 1000, 2 ) : 0,
                    'by_source'   => $by_source,
                );
            }
        }

        return array(
            'posts'      => $posts,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        );
    }

    /**
     * Get revenue for a single post (used by Posts list column).
     *
     * @param int $post_id The post ID.
     * @return float
     */
    public function get_post_revenue_last_30_days( $post_id ) {
        $cache_key = 'post_rev_30d_' . $post_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return (float) $cached;
        }

        $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date   = gmdate( 'Y-m-d' );

        $revenue = RevenueModel::get_post_revenue( $post_id, $start_date, $end_date );

        set_transient( $cache_key, $revenue, HOUR_IN_SECONDS );

        return $revenue;
    }
}
