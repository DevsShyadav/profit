<?php
/**
 * Google Analytics GA4 Integration.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

use ProfitPerPost\Database\TrafficModel;
use ProfitPerPost\Revenue\UrlMatcher;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GoogleAnalytics
 *
 * Integrates with Google Analytics 4 (GA4) Data API
 * to fetch pageview and traffic data per page/post.
 */
class GoogleAnalytics extends BaseIntegration {

    /**
     * GA4 Data API base URL.
     *
     * @var string
     */
    const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

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
     * Google userinfo endpoint.
     *
     * @var string
     */
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * Required OAuth scopes.
     *
     * @var array
     */
    const SCOPES = array(
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source_id      = 'google_analytics';
        $this->source_name    = __( 'Google Analytics', 'profit-per-post' );
        $this->description    = __( 'Connect to Google Analytics 4 to track pageviews and traffic per post.', 'profit-per-post' );
        $this->icon           = 'analytics';
        $this->requires_oauth = true;
    }

    /**
     * Connect with OAuth credentials (client ID, secret, and tokens).
     *
     * @param array $credentials {
     *     @type string $client_id     Google OAuth client ID.
     *     @type string $client_secret Google OAuth client secret.
     *     @type string $property_id   GA4 property ID.
     *     @type string $access_token  OAuth access token (if already obtained).
     *     @type string $refresh_token OAuth refresh token.
     * }
     * @return array Result array.
     */
    public function connect( $credentials ) {
        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Client ID and Client Secret are required.', 'profit-per-post' ),
            );
        }

        if ( empty( $credentials['property_id'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'GA4 Property ID is required.', 'profit-per-post' ),
            );
        }

        // Store credentials.
        $store_data = array(
            'client_id'     => sanitize_text_field( $credentials['client_id'] ),
            'client_secret' => sanitize_text_field( $credentials['client_secret'] ),
            'property_id'   => sanitize_text_field( $credentials['property_id'] ),
            'access_token'  => isset( $credentials['access_token'] ) ? $credentials['access_token'] : '',
            'refresh_token' => isset( $credentials['refresh_token'] ) ? $credentials['refresh_token'] : '',
            'token_expiry'  => isset( $credentials['token_expiry'] ) ? $credentials['token_expiry'] : 0,
        );

        $expires_at = ! empty( $store_data['token_expiry'] )
            ? gmdate( 'Y-m-d H:i:s', $store_data['token_expiry'] )
            : null;

        $account_info = '';
        if ( ! empty( $store_data['access_token'] ) ) {
            $user_info = $this->fetch_user_info( $store_data['access_token'] );
            if ( $user_info ) {
                $account_info = $user_info;
            }
        }

        $stored = $this->store_connection( $store_data, 'connected', $account_info, $expires_at );

        if ( ! $stored ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to store credentials.', 'profit-per-post' ),
            );
        }

        return array(
            'success' => true,
            'message' => __( 'Google Analytics connected successfully.', 'profit-per-post' ),
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

        $redirect_uri = admin_url( 'admin.php?page=profit-per-post&ppp_oauth_callback=google_analytics' );

        $params = array(
            'client_id'     => $credentials['client_id'],
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => implode( ' ', self::SCOPES ),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce( 'ppp_oauth_ga' ),
        );

        return self::AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Handle OAuth callback - exchange code for tokens.
     *
     * @param string $code The authorization code.
     * @return array Result array.
     */
    public function handle_oauth_callback( $code ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials ) {
            return array(
                'success' => false,
                'message' => __( 'No stored credentials found. Please configure Client ID and Secret first.', 'profit-per-post' ),
            );
        }

        $redirect_uri = admin_url( 'admin.php?page=profit-per-post&ppp_oauth_callback=google_analytics' );

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

        // Update credentials with tokens.
        $credentials['access_token']  = $body['access_token'];
        $credentials['refresh_token'] = isset( $body['refresh_token'] ) ? $body['refresh_token'] : $credentials['refresh_token'];
        $credentials['token_expiry']  = time() + (int) $body['expires_in'];

        // Fetch user info for display.
        $account_info = $this->fetch_user_info( $body['access_token'] );

        $expires_at = gmdate( 'Y-m-d H:i:s', $credentials['token_expiry'] );

        $this->store_connection( $credentials, 'connected', $account_info, $expires_at );

        return array(
            'success' => true,
            'message' => __( 'Google Analytics connected successfully.', 'profit-per-post' ),
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

        // Update stored credentials.
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
     * Test if the current connection is working.
     *
     * @return array
     */
    public function test_connection() {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['access_token'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Not connected. No access token found.', 'profit-per-post' ),
            );
        }

        // Check if token needs refresh.
        if ( ! empty( $credentials['token_expiry'] ) && $credentials['token_expiry'] < time() ) {
            if ( ! $this->refresh_token() ) {
                return array(
                    'success' => false,
                    'message' => __( 'Token expired and could not be refreshed.', 'profit-per-post' ),
                );
            }
            $credentials = $this->get_credentials();
        }

        // Make a simple API request to verify access.
        $property_id = $credentials['property_id'];
        $url         = self::API_BASE . "/properties/{$property_id}/metadata";

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
            'message' => __( 'Google Analytics connection is working.', 'profit-per-post' ),
        );
    }

    /**
     * Sync traffic data from Google Analytics.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync( $start_date, $end_date ) {
        $credentials = $this->get_credentials();

        if ( ! $credentials || empty( $credentials['access_token'] ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Not connected.', 'profit-per-post' ),
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

        $property_id = $credentials['property_id'];
        $url         = self::API_BASE . "/properties/{$property_id}:runReport";

        // Build the report request.
        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $start_date,
                    'endDate'   => $end_date,
                ),
            ),
            'dimensions' => array(
                array( 'name' => 'pagePath' ),
                array( 'name' => 'date' ),
            ),
            'metrics' => array(
                array( 'name' => 'screenPageViews' ),
                array( 'name' => 'totalUsers' ),
                array( 'name' => 'averageSessionDuration' ),
                array( 'name' => 'bounceRate' ),
            ),
            'limit'  => 10000,
            'offset' => 0,
        );

        $all_rows      = array();
        $has_more_data = true;
        $offset        = 0;

        while ( $has_more_data ) {
            $request_body['offset'] = $offset;

            $response = $this->make_request( $url, array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $credentials['access_token'],
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( $request_body ),
            ));

            if ( is_wp_error( $response ) ) {
                return array(
                    'success'        => false,
                    'records_synced' => 0,
                    'message'        => $response->get_error_message(),
                );
            }

            $data = $response['body'];

            if ( empty( $data['rows'] ) ) {
                break;
            }

            $all_rows = array_merge( $all_rows, $data['rows'] );

            $row_count = isset( $data['rowCount'] ) ? (int) $data['rowCount'] : 0;
            $offset   += 10000;

            if ( $offset >= $row_count || count( $data['rows'] ) < 10000 ) {
                $has_more_data = false;
            }
        }

        // Process and store the data.
        $records_synced = $this->process_ga_data( $all_rows );

        // Update last synced.
        $this->update_last_synced();

        return array(
            'success'        => true,
            'records_synced' => $records_synced,
            'message'        => sprintf(
                /* translators: %d: Number of records synced */
                __( 'Synced %d traffic records from Google Analytics.', 'profit-per-post' ),
                $records_synced
            ),
        );
    }

    /**
     * Process GA4 API response rows and store in database.
     *
     * @param array $rows The API response rows.
     * @return int Number of records processed.
     */
    private function process_ga_data( $rows ) {
        if ( empty( $rows ) ) {
            return 0;
        }

        $url_matcher = new UrlMatcher();
        $records     = array();

        foreach ( $rows as $row ) {
            $page_path  = isset( $row['dimensionValues'][0]['value'] ) ? $row['dimensionValues'][0]['value'] : '';
            $date_raw   = isset( $row['dimensionValues'][1]['value'] ) ? $row['dimensionValues'][1]['value'] : '';
            $pageviews  = isset( $row['metricValues'][0]['value'] ) ? (int) $row['metricValues'][0]['value'] : 0;
            $users      = isset( $row['metricValues'][1]['value'] ) ? (int) $row['metricValues'][1]['value'] : 0;
            $avg_time   = isset( $row['metricValues'][2]['value'] ) ? (float) $row['metricValues'][2]['value'] : 0;
            $bounce     = isset( $row['metricValues'][3]['value'] ) ? (float) $row['metricValues'][3]['value'] * 100 : 0;

            // Skip empty paths.
            if ( empty( $page_path ) || '/' === $page_path ) {
                continue;
            }

            // Match URL to post ID.
            $post_id = $url_matcher->match( $page_path );
            if ( ! $post_id ) {
                continue;
            }

            // Format date from YYYYMMDD to Y-m-d.
            $date_formatted = '';
            if ( strlen( $date_raw ) === 8 ) {
                $date_formatted = substr( $date_raw, 0, 4 ) . '-' . substr( $date_raw, 4, 2 ) . '-' . substr( $date_raw, 6, 2 );
            } else {
                continue;
            }

            $records[] = array(
                'post_id'          => $post_id,
                'pageviews'        => $pageviews,
                'unique_visitors'  => $users,
                'avg_time_on_page' => $avg_time,
                'bounce_rate'      => $bounce,
                'date_recorded'    => $date_formatted,
                'source'           => 'google_analytics',
            );
        }

        // Bulk upsert records.
        return TrafficModel::bulk_upsert( $records );
    }

    /**
     * Fetch user info from Google.
     *
     * @param string $access_token The access token.
     * @return string Account info string (email).
     */
    private function fetch_user_info( $access_token ) {
        $response = wp_remote_get( self::USERINFO_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['email'] ) ) {
            return sanitize_email( $body['email'] );
        }

        return '';
    }
}
