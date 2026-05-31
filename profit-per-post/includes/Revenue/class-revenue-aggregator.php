<?php
/**
 * Revenue Aggregator - combines multiple revenue sources.
 *
 * @package ProfitPerPost\Revenue
 */

namespace ProfitPerPost\Revenue;

use ProfitPerPost\Database\RevenueModel;
use ProfitPerPost\Database\TrafficModel;
use ProfitPerPost\Database\AffiliateModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RevenueAggregator
 *
 * Aggregates revenue data from multiple sources into unified summaries.
 * Used for generating reports and export data.
 */
class RevenueAggregator {

    /**
     * Aggregate revenue data for all posts in a date range.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Aggregated data.
     */
    public function aggregate_all( $start_date, $end_date ) {
        $all_posts_revenue = RevenueModel::get_all_posts_revenue( $start_date, $end_date, 10000, 0, 'DESC' );
        $all_posts_traffic = TrafficModel::get_all_posts_traffic( $start_date, $end_date, 10000, 0 );

        // Index traffic by post_id.
        $traffic_index = array();
        foreach ( $all_posts_traffic as $t ) {
            $traffic_index[ (int) $t['post_id'] ] = $t;
        }

        $aggregated = array();

        foreach ( $all_posts_revenue as $rev_data ) {
            $post_id = (int) $rev_data['post_id'];
            $post    = get_post( $post_id );

            if ( ! $post ) {
                continue;
            }

            $traffic = isset( $traffic_index[ $post_id ] ) ? $traffic_index[ $post_id ] : null;
            $pageviews = $traffic ? (int) $traffic['total_pageviews'] : 0;
            $revenue   = (float) $rev_data['total_revenue'];

            $by_source = RevenueModel::get_post_revenue_by_source( $post_id, $start_date, $end_date );
            $affiliate_clicks = AffiliateModel::get_post_clicks( $post_id, $start_date, $end_date );

            $aggregated[] = array(
                'post_id'          => $post_id,
                'title'            => $post->post_title,
                'url'              => get_permalink( $post_id ),
                'author'           => get_the_author_meta( 'display_name', $post->post_author ),
                'categories'       => implode( ', ', wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ) ),
                'published_date'   => $post->post_date,
                'total_revenue'    => round( $revenue, 2 ),
                'pageviews'        => $pageviews,
                'rpm'              => $pageviews > 0 ? round( ( $revenue / $pageviews ) * 1000, 2 ) : 0,
                'adsense_revenue'  => isset( $by_source['adsense'] ) ? round( $by_source['adsense'], 2 ) : 0,
                'mediavine_revenue' => isset( $by_source['mediavine'] ) ? round( $by_source['mediavine'], 2 ) : 0,
                'wc_revenue'       => isset( $by_source['woocommerce'] ) ? round( $by_source['woocommerce'], 2 ) : 0,
                'affiliate_revenue' => isset( $by_source['affiliate'] ) ? round( $by_source['affiliate'], 2 ) : 0,
                'affiliate_clicks' => $affiliate_clicks,
            );
        }

        return $aggregated;
    }

    /**
     * Generate monthly summary aggregation.
     *
     * @param int $months Number of months to aggregate.
     * @return array Monthly summaries.
     */
    public function aggregate_monthly( $months = 12 ) {
        $summaries = array();

        for ( $i = 0; $i < $months; $i++ ) {
            $start = gmdate( 'Y-m-01', strtotime( "-{$i} months" ) );
            $end   = gmdate( 'Y-m-t', strtotime( "-{$i} months" ) );

            $revenue   = RevenueModel::get_total_revenue( $start, $end );
            $pageviews = TrafficModel::get_total_pageviews( $start, $end );
            $by_source = RevenueModel::get_revenue_by_source( $start, $end );

            $summaries[] = array(
                'month'     => gmdate( 'Y-m', strtotime( "-{$i} months" ) ),
                'label'     => gmdate( 'M Y', strtotime( "-{$i} months" ) ),
                'revenue'   => round( $revenue, 2 ),
                'pageviews' => $pageviews,
                'rpm'       => $pageviews > 0 ? round( ( $revenue / $pageviews ) * 1000, 2 ) : 0,
                'by_source' => $by_source,
            );
        }

        return array_reverse( $summaries );
    }

    /**
     * Get comparative data between two periods.
     *
     * @param string $current_start Current period start.
     * @param string $current_end   Current period end.
     * @param string $prev_start    Previous period start.
     * @param string $prev_end      Previous period end.
     * @return array Comparison data.
     */
    public function compare_periods( $current_start, $current_end, $prev_start, $prev_end ) {
        $current_revenue   = RevenueModel::get_total_revenue( $current_start, $current_end );
        $prev_revenue      = RevenueModel::get_total_revenue( $prev_start, $prev_end );

        $current_pageviews = TrafficModel::get_total_pageviews( $current_start, $current_end );
        $prev_pageviews    = TrafficModel::get_total_pageviews( $prev_start, $prev_end );

        $current_by_source = RevenueModel::get_revenue_by_source( $current_start, $current_end );
        $prev_by_source    = RevenueModel::get_revenue_by_source( $prev_start, $prev_end );

        return array(
            'current' => array(
                'revenue'   => round( $current_revenue, 2 ),
                'pageviews' => $current_pageviews,
                'by_source' => $current_by_source,
                'period'    => array( 'start' => $current_start, 'end' => $current_end ),
            ),
            'previous' => array(
                'revenue'   => round( $prev_revenue, 2 ),
                'pageviews' => $prev_pageviews,
                'by_source' => $prev_by_source,
                'period'    => array( 'start' => $prev_start, 'end' => $prev_end ),
            ),
            'changes' => array(
                'revenue_change'   => $prev_revenue > 0 ? round( ( ( $current_revenue - $prev_revenue ) / $prev_revenue ) * 100, 1 ) : 0,
                'pageviews_change' => $prev_pageviews > 0 ? round( ( ( $current_pageviews - $prev_pageviews ) / $prev_pageviews ) * 100, 1 ) : 0,
            ),
        );
    }

    /**
     * Generate insights/recommendations based on revenue data.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Array of insight strings.
     */
    public function generate_insights( $start_date, $end_date ) {
        $insights    = array();
        $top_posts   = RevenueModel::get_all_posts_revenue( $start_date, $end_date, 10, 0, 'DESC' );
        $dead_count  = RevenueModel::get_dead_posts_count( $start_date, $end_date );
        $total_rev   = RevenueModel::get_total_revenue( $start_date, $end_date );
        $by_source   = RevenueModel::get_revenue_by_source( $start_date, $end_date );

        // Insight: Revenue concentration.
        if ( ! empty( $top_posts ) && $total_rev > 0 ) {
            $top_10_rev = 0;
            foreach ( $top_posts as $p ) {
                $top_10_rev += (float) $p['total_revenue'];
            }
            $concentration = round( ( $top_10_rev / $total_rev ) * 100 );

            if ( $concentration > 80 ) {
                $insights[] = array(
                    'type'    => 'warning',
                    'message' => sprintf(
                        /* translators: %d: Percentage */
                        __( 'Your top 10 posts generate %d%% of total revenue. Consider diversifying.', 'profit-per-post' ),
                        $concentration
                    ),
                );
            }
        }

        // Insight: Dead posts.
        if ( $dead_count > 0 ) {
            $insights[] = array(
                'type'    => 'info',
                'message' => sprintf(
                    /* translators: %d: Number of dead posts */
                    __( 'You have %d posts generating $0 revenue. Consider updating or consolidating them.', 'profit-per-post' ),
                    $dead_count
                ),
            );
        }

        // Insight: Dominant source.
        if ( ! empty( $by_source ) && $total_rev > 0 ) {
            $top_source     = array_key_first( $by_source );
            $top_source_pct = round( ( $by_source[ $top_source ] / $total_rev ) * 100 );

            if ( $top_source_pct > 70 ) {
                $source_labels = array(
                    'adsense'     => 'AdSense',
                    'mediavine'   => 'Mediavine',
                    'woocommerce' => 'WooCommerce',
                    'affiliate'   => 'Affiliate Links',
                );
                $label = isset( $source_labels[ $top_source ] ) ? $source_labels[ $top_source ] : $top_source;

                $insights[] = array(
                    'type'    => 'tip',
                    'message' => sprintf(
                        /* translators: 1: Percentage, 2: Source name */
                        __( '%1$d%% of your revenue comes from %2$s. Consider adding more revenue sources.', 'profit-per-post' ),
                        $top_source_pct,
                        $label
                    ),
                );
            }
        }

        return $insights;
    }
}
