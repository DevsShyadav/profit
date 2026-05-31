<?php
/**
 * Mediavine Integration.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

use ProfitPerPost\Database\RevenueModel;
use ProfitPerPost\Revenue\UrlMatcher;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Mediavine
 *
 * Integrates with Mediavine Reporting API to fetch
 * ad revenue data per post/URL.
 */
class Mediavine extends BaseIntegration {

    /**
     * Mediavine API base URL.
     *
     * @var string
     */
    const API_BASE = 'https://reporting.mediavine.com/api/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source_id        = 'mediavine';
        $this->source_name      = __( 'Mediavine', 'profit-per-post' );
        $this->description      = __( 'Connect to Mediavine to track ad revenue per post.', 'profit-per-post' );
        $this->icon             = 'chart-bar';
        $this->requires_api_key = true;
    }

    /**
     * Connect with API key credentials.
     *
     * @param array $credentials {
     *     @type string $api_key  Mediavine API key/token.
     *     @type string $site_id  Mediavine site ID.
     * }
     * @return array Result array.
     */
    public function connect( $credentials ) {
        if ( empty( $credentials['api_key'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Mediavine API key is required.', 'profit-per-post' ),
            );
        }

        if ( empty( $credentials['site_id'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Mediavine Site ID is required.', 'profit-per-post' ),
            );
        }

        $store_data = array(
            'api_key' => sanitize_text_field( $credentials['api_key'] ),
            'site_id' => sanitize_text_field( $credentials['site_id'] ),
        );

        // Test the API key.
        $test_result = $this->test_api_key( $store_data['api_key'], $store_data['site_id'] );

        if ( ! $test_result['success'] ) {
            return $test_result;
        }

        $stored = $this->store_connection(
            $store_data,
            'connected',
            'Site: ' . $store_data['site_id']
        );

        if ( ! $stored ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to store credentials.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Mediavine connected successfully.', 'profit-per-post' ),
        );
    }

    /**
     * Test connection.
     *
     * @return array
     */
    public function test_connection() {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['api_key'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Not connected.', 'profit-per-post' ),
            );
        }

        return $this->test_api_key( $credentials['api_key'], $credentials['site_id'] );
    }

    /**
     * Test the Mediavine API key.
     *
     * @param string $api_key The API key.
     * @param string $site_id The site ID.
     * @return array
     */
    private function test_api_key( $api_key, $site_id ) {
        $url = self::API_BASE . '/sites/' . $site_id . '/revenue';

        $today     = gmdate( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

        $url .= '?' . http_build_query( array(
            'start_date' => $yesterday,
            'end_date'   => $today,
            'group_by'   => 'date',
        ));

        $response = $this->make_request( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Mediavine connection is working.', 'profit-per-post' ),
        );
    }

    /**
     * Sync revenue data from Mediavine.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync( $start_date, $end_date ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['api_key'] ) || empty( $credentials['site_id'] ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Not connected.', 'profit-per-post' ),
            );
        }

        $site_id = $credentials['site_id'];
        $api_key = $credentials['api_key'];

        // Fetch revenue grouped by URL and date.
        $url = self::API_BASE . '/sites/' . $site_id . '/revenue';
        $url .= '?' . http_build_query( array(
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'group_by'   => 'url,date',
            'limit'      => 10000,
        ));

        $response = $this->make_request( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => $response->get_error_message(),
            );
        }

        $data = $response['body'];

        // Process response data.
        $records_synced = $this->process_mediavine_data( $data );

        $this->update_last_synced();

        return array(
            'success'        => true,
            'records_synced' => $records_synced,
            'message'        => sprintf(
                /* translators: %d: Number of records synced */
                __( 'Synced %d revenue records from Mediavine.', 'profit-per-post' ),
                $records_synced
            ),
        );
    }

    /**
     * Process Mediavine API response data.
     *
     * @param array $data The API response data.
     * @return int Number of records processed.
     */
    private function process_mediavine_data( $data ) {
        $rows = isset( $data['data'] ) ? $data['data'] : ( isset( $data['results'] ) ? $data['results'] : array() );

        if ( empty( $rows ) ) {
            return 0;
        }

        $url_matcher = new UrlMatcher();
        $records     = array();

        foreach ( $rows as $row ) {
            $page_url = isset( $row['url'] ) ? $row['url'] : ( isset( $row['page_url'] ) ? $row['page_url'] : '' );
            $revenue  = isset( $row['revenue'] ) ? (float) $row['revenue'] : ( isset( $row['earnings'] ) ? (float) $row['earnings'] : 0 );
            $date     = isset( $row['date'] ) ? $row['date'] : '';

            if ( empty( $page_url ) || $revenue <= 0 || empty( $date ) ) {
                continue;
            }

            // Parse URL to get path.
            $parsed = wp_parse_url( $page_url );
            $path   = isset( $parsed['path'] ) ? $parsed['path'] : $page_url;

            // Match to post ID.
            $post_id = $url_matcher->match( $path );
            if ( ! $post_id ) {
                continue;
            }

            $records[] = array(
                'post_id'        => $post_id,
                'source'         => 'mediavine',
                'revenue_amount' => $revenue,
                'currency'       => 'USD',
                'date_recorded'  => $date,
                'meta_data'      => null,
            );
        }

        return RevenueModel::bulk_upsert( $records );
    }
}
