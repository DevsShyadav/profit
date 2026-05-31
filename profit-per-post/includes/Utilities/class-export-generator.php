<?php
/**
 * Export Generator - CSV/data export functionality.
 *
 * @package ProfitPerPost\Utilities
 */

namespace ProfitPerPost\Utilities;

use ProfitPerPost\Revenue\RevenueAggregator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ExportGenerator
 *
 * Generates CSV exports of revenue data.
 */
class ExportGenerator {

    /**
     * Generate a CSV export of all post revenue data.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return string CSV content.
     */
    public static function generate_csv( $start_date, $end_date ) {
        $aggregator = new RevenueAggregator();
        $data       = $aggregator->aggregate_all( $start_date, $end_date );

        $currency = get_option( 'ppp_currency', 'USD' );

        // CSV headers.
        $headers = array(
            'Post ID',
            'Title',
            'URL',
            'Author',
            'Categories',
            'Published Date',
            'Total Revenue (' . $currency . ')',
            'Pageviews',
            'RPM (' . $currency . ')',
            'AdSense Revenue',
            'Mediavine Revenue',
            'WooCommerce Revenue',
            'Affiliate Revenue',
            'Affiliate Clicks',
        );

        // Open output buffer.
        $output = fopen( 'php://temp', 'r+' );

        // Add BOM for Excel compatibility.
        fwrite( $output, "\xEF\xBB\xBF" );

        // Write headers.
        fputcsv( $output, $headers );

        // Write data rows.
        foreach ( $data as $row ) {
            fputcsv( $output, array(
                $row['post_id'],
                $row['title'],
                $row['url'],
                $row['author'],
                $row['categories'],
                $row['published_date'],
                $row['total_revenue'],
                $row['pageviews'],
                $row['rpm'],
                $row['adsense_revenue'],
                $row['mediavine_revenue'],
                $row['wc_revenue'],
                $row['affiliate_revenue'],
                $row['affiliate_clicks'],
            ));
        }

        // Get content.
        rewind( $output );
        $csv_content = stream_get_contents( $output );
        fclose( $output );

        return $csv_content;
    }

    /**
     * Generate export filename.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @param string $format     File format (csv).
     * @return string
     */
    public static function get_filename( $start_date, $end_date, $format = 'csv' ) {
        $site_name = sanitize_file_name( get_bloginfo( 'name' ) );
        return "profit-per-post-{$site_name}-{$start_date}-to-{$end_date}.{$format}";
    }

    /**
     * Send CSV download headers and output.
     *
     * @param string $csv_content The CSV content.
     * @param string $filename    The download filename.
     * @return void
     */
    public static function send_csv_download( $csv_content, $filename ) {
        // Clean any previous output.
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $csv_content ) );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $csv_content;
        exit;
    }

    /**
     * Generate a summary report as array (for JSON export).
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array
     */
    public static function generate_json_report( $start_date, $end_date ) {
        $aggregator = new RevenueAggregator();

        return array(
            'report_generated' => current_time( 'mysql' ),
            'period'           => array(
                'start' => $start_date,
                'end'   => $end_date,
            ),
            'site'             => array(
                'name' => get_bloginfo( 'name' ),
                'url'  => home_url(),
            ),
            'currency'         => get_option( 'ppp_currency', 'USD' ),
            'data'             => $aggregator->aggregate_all( $start_date, $end_date ),
            'monthly_summary'  => $aggregator->aggregate_monthly( 12 ),
        );
    }
}
