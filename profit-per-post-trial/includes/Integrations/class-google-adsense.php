<?php
/**
 * Google AdSense Integration.
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
 * Class GoogleAdsense
 *
 * Integrates with Google AdSense Management API
 * to fetch ad revenue data per URL/page.
 */
class GoogleAdsense extends BaseIntegration {

    /**
     * AdSense API base URL.
     *
     * @var string
     */
    const API_BASE = 'https://adsense.googleapis.com/v2';

    /**
     * Google OAuth2 token endpoint.
     *
     * @var string
     */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Google OAuth2 authorization endpoint.
     *
     * @var string
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * Required OAuth scopes.
     *
     * @var array
     */
    const SCOPES = array(
        'https://www.googleapis.com/auth/adsense.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source_id      = 'adsense';
        $this->source_name    = __( 'Google AdSense', 'profit-per-post' );
        $this->description    = __( 'Connect to Google AdSense to track ad revenue per page/post.', 'profit-per-post' );
        $this->icon           = 'money-alt';
        $this->requires_oauth = true;
    }

    /**
     * Connect with credentials.
     *
     * @param array $credentials The OAuth credentials.
     * @return array Result array.
     */
    public function connect( $credentials ) {
        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Client ID and Client Secret are required.', 'profit-per-post' ),
            );
        }

        $store_data = array(
            'client_id'     => sanitize_text_field( $credentials['client_id'] ),
            'client_secret' => sanitize_text_field( $credentials['client_secret'] ),
            'account_id'    => isset( $credentials['account_id'] ) ? sanitize_text_field( $credentials['account_id'] ) : '',
            'access_token'  => isset( $credentials['access_token'] ) ? $credentials['access_token'] : '',
            'refresh_token' => isset( $credentials['refresh_token'] ) ? $credentials['refresh_token'] : '',
            'token_expiry'  => isset( $credentials['token_expiry'] ) ? $credentials['token_expiry'] : 0,
        );

        $expires_at = ! empty( $store_data['token_expiry'] )
            ? gmdate( 'Y-m-d H:i:s', $store_data['token_expiry'] )
            : null;

        $stored = $this->store_connection( $store_data, 'connected', '', $expires_at );

        if ( ! $stored ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to store credentials.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Google AdSense connected successfully.', 'profit-per-post' ),
        );
    }

    /**
     * Get the OAuth authorization URL.
     *
     * @return string|false
     */
    public function get_auth_url() {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['client_id'] ) ) {
            return false;
        }

        $redirect_uri = admin_url( 'admin.php?page=profit-per-post&ppp_oauth_callback=adsense' );

        $params = array(
            'client_id'     => $credentials['client_id'],
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => implode( ' ', self::SCOPES ),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce( 'ppp_oauth_adsense' ),
        );

        return self::AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Handle OAuth callback.
     *
     * @param string $code The authorization code.
     * @return array Result array.
     */
    public function handle_oauth_callback( $code ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials ) {
            return array(
                'success' => false,
                'message' => __( 'No stored credentials found.', 'profit-per-post' ),
            );
        }

        $redirect_uri = admin_url( 'admin.php?page=profit-per-post&ppp_oauth_callback=adsense' );

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            $error_msg = isset( $body['error_description'] ) ? $body['error_description'] : __( 'Failed to obtain access token.', 'profit-per-post' );
            return array(
                'success' => false,
                'message' => $error_msg,
            );
        }

        $credentials['access_token']  = $body['access_token'];
        $credentials['refresh_token'] = isset( $body['refresh_token'] ) ? $body['refresh_token'] : $credentials['refresh_token'];
        $credentials['token_expiry']  = time() + (int) $body['expires_in'];

        // Auto-detect AdSense account ID.
        if ( empty( $credentials['account_id'] ) ) {
            $account_id = $this->detect_account_id( $body['access_token'] );
            if ( $account_id ) {
                $credentials['account_id'] = $account_id;
            }
        }

        $expires_at = gmdate( 'Y-m-d H:i:s', $credentials['token_expiry'] );
        $this->store_connection( $credentials, 'connected', $credentials['account_id'], $expires_at );

        return array(
            'success' => true,
            'message' => __( 'Google AdSense connected successfully.', 'profit-per-post' ),
        );
    }

    /**
     * Refresh the OAuth access token.
     *
     * @return bool
     */
    protected function refresh_token() {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['refresh_token'] ) ) {
            return false;
        }

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
                'grant_type'    => 'refresh_token',
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            $this->update_status( 'expired' );
            return false;
        }

        $credentials['access_token'] = $body['access_token'];
        $credentials['token_expiry'] = time() + (int) $body['expires_in'];

        if ( isset( $body['refresh_token'] ) ) {
            $credentials['refresh_token'] = $body['refresh_token'];
        }

        $expires_at = gmdate( 'Y-m-d H:i:s', $credentials['token_expiry'] );
        $this->store_connection( $credentials, 'connected', '', $expires_at );

        return true;
    }

    /**
     * Test connection.
     *
     * @return array
     */
    public function test_connection() {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['access_token'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Not connected.', 'profit-per-post' ),
            );
        }

        if ( ! empty( $credentials['token_expiry'] ) && $credentials['token_expiry'] < time() ) {
            if ( ! $this->refresh_token() ) {
                return array(
                    'success' => false,
                    'message' => __( 'Token expired and could not be refreshed.', 'profit-per-post' ),
                );
            }
            $credentials = $this->get_credentials();
        }

        $url = self::API_BASE . '/accounts';

        $response = $this->make_request( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $credentials['access_token'],
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
            'message' => __( 'Google AdSense connection is working.', 'profit-per-post' ),
        );
    }

    /**
     * Sync revenue data from AdSense.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync( $start_date, $end_date ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['access_token'] ) || empty( $credentials['account_id'] ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Not connected or missing account ID.', 'profit-per-post' ),
            );
        }

        // Refresh token if needed.
        if ( ! empty( $credentials['token_expiry'] ) && $credentials['token_expiry'] < time() ) {
            if ( ! $this->refresh_token() ) {
                return array(
                    'success'        => false,
                    'records_synced' => 0,
                    'message'        => __( 'Token expired.', 'profit-per-post' ),
                );
            }
            $credentials = $this->get_credentials();
        }

        $account_id = $credentials['account_id'];

        // Build report URL with query params.
        $params = array(
            'dateRange'          => 'CUSTOM',
            'startDate.year'     => (int) substr( $start_date, 0, 4 ),
            'startDate.month'    => (int) substr( $start_date, 5, 2 ),
            'startDate.day'      => (int) substr( $start_date, 8, 2 ),
            'endDate.year'       => (int) substr( $end_date, 0, 4 ),
            'endDate.month'      => (int) substr( $end_date, 5, 2 ),
            'endDate.day'        => (int) substr( $end_date, 8, 2 ),
            'dimensions'         => 'PAGE_URL',
            'metrics'            => 'ESTIMATED_EARNINGS,PAGE_VIEWS,CLICKS',
            'reportingTimeZone'  => 'ACCOUNT_TIME_ZONE',
            'limit'              => 10000,
        );

        // Add date dimension for daily breakdown.
        $params['dimensions'] = 'DATE,PAGE_URL';

        $url = self::API_BASE . "/accounts/{$account_id}/reports:generate?" . http_build_query( $params );

        $response = $this->make_request( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $credentials['access_token'],
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

        // Process rows.
        $records_synced = $this->process_adsense_data( $data );

        $this->update_last_synced();

        return array(
            'success'        => true,
            'records_synced' => $records_synced,
            'message'        => sprintf(
                /* translators: %d: Number of records synced */
                __( 'Synced %d revenue records from AdSense.', 'profit-per-post' ),
                $records_synced
            ),
        );
    }

    /**
     * Process AdSense API response data.
     *
     * @param array $data The API response data.
     * @return int Number of records processed.
     */
    private function process_adsense_data( $data ) {
        if ( empty( $data['rows'] ) ) {
            return 0;
        }

        $url_matcher = new UrlMatcher();
        $records     = array();

        foreach ( $data['rows'] as $row ) {
            $cells = isset( $row['cells'] ) ? $row['cells'] : array();

            if ( count( $cells ) < 5 ) {
                continue;
            }

            // Dimensions: DATE, PAGE_URL.
            $date_raw  = isset( $cells[0]['value'] ) ? $cells[0]['value'] : '';
            $page_url  = isset( $cells[1]['value'] ) ? $cells[1]['value'] : '';

            // Metrics: ESTIMATED_EARNINGS, PAGE_VIEWS, CLICKS.
            $earnings  = isset( $cells[2]['value'] ) ? (float) $cells[2]['value'] : 0;

            if ( empty( $page_url ) || $earnings <= 0 ) {
                continue;
            }

            // Parse URL to get path.
            $parsed_url = wp_parse_url( $page_url );
            $page_path  = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

            if ( empty( $page_path ) ) {
                continue;
            }

            // Match URL to post ID.
            $post_id = $url_matcher->match( $page_path );
            if ( ! $post_id ) {
                continue;
            }

            // Format date.
            $date_formatted = $date_raw;
            if ( strlen( $date_raw ) === 8 ) {
                $date_formatted = substr( $date_raw, 0, 4 ) . '-' . substr( $date_raw, 4, 2 ) . '-' . substr( $date_raw, 6, 2 );
            }

            $records[] = array(
                'post_id'        => $post_id,
                'source'         => 'adsense',
                'revenue_amount' => $earnings,
                'currency'       => 'USD',
                'date_recorded'  => $date_formatted,
                'meta_data'      => null,
            );
        }

        return RevenueModel::bulk_upsert( $records );
    }

    /**
     * Auto-detect the AdSense account ID.
     *
     * @param string $access_token The access token.
     * @return string|false
     */
    private function detect_account_id( $access_token ) {
        $url = self::API_BASE . '/accounts';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['accounts'][0]['name'] ) ) {
            return $body['accounts'][0]['name'];
        }

        return false;
    }
}
