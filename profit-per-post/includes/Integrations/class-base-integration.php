<?php
/**
 * Base Integration abstract class.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

use ProfitPerPost\Database\Schema;
use ProfitPerPost\Security\Encryption;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BaseIntegration
 *
 * Abstract base class that all revenue source integrations must extend.
 * Provides common functionality for connection management, credential storage,
 * and sync lifecycle methods.
 */
abstract class BaseIntegration {

    /**
     * Integration identifier (e.g., 'google_analytics', 'adsense').
     *
     * @var string
     */
    protected $source_id = '';

    /**
     * Human-readable integration name.
     *
     * @var string
     */
    protected $source_name = '';

    /**
     * Integration description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Integration icon (dashicon or SVG path).
     *
     * @var string
     */
    protected $icon = '';

    /**
     * Whether this integration requires OAuth authentication.
     *
     * @var bool
     */
    protected $requires_oauth = false;

    /**
     * Whether this integration requires an API key.
     *
     * @var bool
     */
    protected $requires_api_key = false;

    /**
     * Whether this integration is a local/internal integration (no external API).
     *
     * @var bool
     */
    protected $is_internal = false;

    /**
     * Cached connection data.
     *
     * @var array|null
     */
    protected $connection = null;

    /**
     * Get the source identifier.
     *
     * @return string
     */
    public function get_source_id() {
        return $this->source_id;
    }

    /**
     * Get the source name.
     *
     * @return string
     */
    public function get_source_name() {
        return $this->source_name;
    }

    /**
     * Get the description.
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get the icon.
     *
     * @return string
     */
    public function get_icon() {
        return $this->icon;
    }

    /**
     * Check if integration requires OAuth.
     *
     * @return bool
     */
    public function requires_oauth() {
        return $this->requires_oauth;
    }

    /**
     * Check if integration requires an API key.
     *
     * @return bool
     */
    public function requires_api_key() {
        return $this->requires_api_key;
    }

    /**
     * Check if integration is internal (no external API needed).
     *
     * @return bool
     */
    public function is_internal() {
        return $this->is_internal;
    }

    /**
     * Get integration info as array.
     *
     * @return array
     */
    public function get_info() {
        return array(
            'source_id'        => $this->source_id,
            'source_name'      => $this->source_name,
            'description'      => $this->description,
            'icon'             => $this->icon,
            'requires_oauth'   => $this->requires_oauth,
            'requires_api_key' => $this->requires_api_key,
            'is_internal'      => $this->is_internal,
            'status'           => $this->get_status(),
            'last_synced'      => $this->get_last_synced(),
        );
    }

    /**
     * Get connection status.
     *
     * @return string One of: 'connected', 'disconnected', 'error', 'expired'.
     */
    public function get_status() {
        $connection = $this->get_connection();

        if ( ! $connection ) {
            return 'disconnected';
        }

        return $connection['status'];
    }

    /**
     * Check if the integration is connected and ready.
     *
     * @return bool
     */
    public function is_connected() {
        return 'connected' === $this->get_status();
    }

    /**
     * Get last synced timestamp.
     *
     * @return string|null
     */
    public function get_last_synced() {
        $connection = $this->get_connection();
        return $connection ? $connection['last_synced_at'] : null;
    }

    /**
     * Get the stored connection record.
     *
     * @return array|null
     */
    protected function get_connection() {
        if ( null !== $this->connection ) {
            return $this->connection;
        }

        global $wpdb;
        $table = Schema::connections_table();

        $this->connection = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source = %s",
                $this->source_id
            ),
            ARRAY_A
        );

        return $this->connection;
    }

    /**
     * Store credentials for this integration.
     *
     * @param array  $credentials The credentials to store.
     * @param string $status      Connection status.
     * @param string $account_info Account display info.
     * @param string $expires_at  Token expiration datetime (optional).
     * @return bool
     */
    protected function store_connection( $credentials, $status = 'connected', $account_info = '', $expires_at = null ) {
        global $wpdb;
        $table = Schema::connections_table();

        $encrypted = Encryption::encrypt_credentials( $credentials );
        if ( false === $encrypted ) {
            return false;
        }

        $existing = $this->get_connection();

        $data = array(
            'source'           => $this->source_id,
            'status'           => $status,
            'credentials'      => $encrypted,
            'account_info'     => $account_info,
            'token_expires_at' => $expires_at,
            'updated_at'       => current_time( 'mysql' ),
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s' );

        if ( $existing ) {
            $result = $wpdb->update(
                $table,
                $data,
                array( 'id' => $existing['id'] ),
                $format,
                array( '%d' )
            );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $format[]           = '%s';
            $result             = $wpdb->insert( $table, $data, $format );
        }

        // Clear cached connection.
        $this->connection = null;

        return false !== $result;
    }

    /**
     * Get decrypted credentials.
     *
     * @return array|false
     */
    protected function get_credentials() {
        $connection = $this->get_connection();

        if ( ! $connection || empty( $connection['credentials'] ) ) {
            return false;
        }

        return Encryption::decrypt_credentials( $connection['credentials'] );
    }

    /**
     * Update connection status.
     *
     * @param string $status The new status.
     * @return bool
     */
    protected function update_status( $status ) {
        global $wpdb;
        $table = Schema::connections_table();

        $connection = $this->get_connection();
        if ( ! $connection ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array(
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $connection['id'] ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        $this->connection = null;

        return false !== $result;
    }

    /**
     * Update last synced timestamp.
     *
     * @return bool
     */
    protected function update_last_synced() {
        global $wpdb;
        $table = Schema::connections_table();

        $connection = $this->get_connection();
        if ( ! $connection ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array( 'last_synced_at' => current_time( 'mysql' ) ),
            array( 'id' => $connection['id'] ),
            array( '%s' ),
            array( '%d' )
        );

        $this->connection = null;

        return false !== $result;
    }

    /**
     * Disconnect the integration.
     *
     * @return bool
     */
    public function disconnect() {
        global $wpdb;
        $table = Schema::connections_table();

        $result = $wpdb->delete(
            $table,
            array( 'source' => $this->source_id ),
            array( '%s' )
        );

        $this->connection = null;

        return false !== $result;
    }

    /**
     * Connect the integration with provided credentials.
     *
     * @param array $credentials The credentials/config data.
     * @return array Result array with 'success' and 'message' keys.
     */
    abstract public function connect( $credentials );

    /**
     * Test if the current connection is working.
     *
     * @return array Result array with 'success' and 'message' keys.
     */
    abstract public function test_connection();

    /**
     * Sync data from this integration.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array with 'success', 'records_synced', and 'message' keys.
     */
    abstract public function sync( $start_date, $end_date );

    /**
     * Initialize frontend tracking (if applicable).
     * Override in subclasses that need frontend functionality.
     *
     * @return void
     */
    public function init_frontend_tracking() {
        // Default: no frontend tracking needed.
    }

    /**
     * Get the OAuth authorization URL (for OAuth-based integrations).
     *
     * @return string|false The authorization URL or false if not applicable.
     */
    public function get_auth_url() {
        return false;
    }

    /**
     * Handle OAuth callback (for OAuth-based integrations).
     *
     * @param string $code The authorization code.
     * @return array Result array with 'success' and 'message' keys.
     */
    public function handle_oauth_callback( $code ) {
        return array(
            'success' => false,
            'message' => __( 'OAuth not supported by this integration.', 'profit-per-post' ),
        );
    }

    /**
     * Refresh OAuth token (for OAuth-based integrations).
     *
     * @return bool Whether the token was successfully refreshed.
     */
    protected function refresh_token() {
        return false;
    }

    /**
     * Make an HTTP request with error handling.
     *
     * @param string $url     The request URL.
     * @param array  $args    Request arguments for wp_remote_request.
     * @return array|\WP_Error Response array or WP_Error.
     */
    protected function make_request( $url, $args = array() ) {
        $defaults = array(
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        // Handle token expiration.
        if ( 401 === $code && $this->requires_oauth ) {
            // Try to refresh token.
            if ( $this->refresh_token() ) {
                // Retry the request.
                $response = wp_remote_request( $url, $args );
                if ( is_wp_error( $response ) ) {
                    return $response;
                }
            } else {
                $this->update_status( 'expired' );
                return new \WP_Error(
                    'ppp_token_expired',
                    __( 'Authentication token expired. Please reconnect.', 'profit-per-post' )
                );
            }
        }

        if ( $code >= 400 ) {
            return new \WP_Error(
                'ppp_api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: Error response body */
                    __( 'API request failed with status %1$d: %2$s', 'profit-per-post' ),
                    $code,
                    wp_trim_words( $body, 50 )
                ),
                array( 'status' => $code, 'body' => $body )
            );
        }

        return array(
            'code' => $code,
            'body' => json_decode( $body, true ),
            'raw'  => $body,
        );
    }
}
