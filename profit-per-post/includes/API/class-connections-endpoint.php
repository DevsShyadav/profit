<?php
/**
 * Connections Endpoint - manage API connections.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Integrations\IntegrationManager;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ConnectionsEndpoint
 *
 * REST endpoints for managing integration connections
 * (connect, disconnect, test, OAuth callbacks).
 */
class ConnectionsEndpoint extends RestController {

    /**
     * Integration manager.
     *
     * @var IntegrationManager
     */
    private $integration_manager;

    /**
     * Constructor.
     *
     * @param IntegrationManager $integration_manager Integration manager.
     */
    public function __construct( IntegrationManager $integration_manager ) {
        $this->integration_manager = $integration_manager;
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        // List all connections/integrations.
        register_rest_route( $this->namespace, '/connections', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_connections' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
        ));

        // Connect a specific source.
        register_rest_route( $this->namespace, '/connections/(?P<source>[a-z_]+)/connect', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'connect_source' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
            'args'                => array(
                'source' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Disconnect a specific source.
        register_rest_route( $this->namespace, '/connections/(?P<source>[a-z_]+)/disconnect', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'disconnect_source' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
            'args'                => array(
                'source' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Test a connection.
        register_rest_route( $this->namespace, '/connections/(?P<source>[a-z_]+)/test', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'test_connection' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
            'args'                => array(
                'source' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Test all connections.
        register_rest_route( $this->namespace, '/connections/test-all', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'test_all_connections' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
        ));

        // Get OAuth URL.
        register_rest_route( $this->namespace, '/connections/(?P<source>[a-z_]+)/auth-url', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_auth_url' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
            'args'                => array(
                'source' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Handle OAuth callback.
        register_rest_route( $this->namespace, '/connections/(?P<source>[a-z_]+)/oauth-callback', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_oauth_callback' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_connections' ),
            'args'                => array(
                'source' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'code' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get all connections status.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_connections( $request ) {
        $info = $this->integration_manager->get_all_info();
        return $this->success( $info );
    }

    /**
     * Connect a source.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function connect_source( $request ) {
        $source      = $request->get_param( 'source' );
        $credentials = $request->get_json_params();

        // Remove the 'source' key if present in body.
        unset( $credentials['source'] );

        $result = $this->integration_manager->connect( $source, $credentials );

        if ( $result['success'] ) {
            return $this->success( $result );
        }

        return $this->error( $result['message'] );
    }

    /**
     * Disconnect a source.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function disconnect_source( $request ) {
        $source = $request->get_param( 'source' );
        $result = $this->integration_manager->disconnect( $source );

        if ( $result['success'] ) {
            return $this->success( $result );
        }

        return $this->error( $result['message'] );
    }

    /**
     * Test a connection.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function test_connection( $request ) {
        $source = $request->get_param( 'source' );
        $result = $this->integration_manager->test_connection( $source );

        if ( $result['success'] ) {
            return $this->success( $result );
        }

        return $this->error( $result['message'] );
    }

    /**
     * Test all connections.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function test_all_connections( $request ) {
        $results = $this->integration_manager->test_all_connections();
        return $this->success( $results );
    }

    /**
     * Get OAuth authorization URL.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_auth_url( $request ) {
        $source = $request->get_param( 'source' );
        $url    = $this->integration_manager->get_auth_url( $source );

        if ( ! $url ) {
            return $this->error( __( 'OAuth not available for this source. Configure Client ID and Secret first.', 'profit-per-post' ) );
        }

        return $this->success( array( 'auth_url' => $url ) );
    }

    /**
     * Handle OAuth callback.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function handle_oauth_callback( $request ) {
        $source = $request->get_param( 'source' );
        $code   = $request->get_param( 'code' );

        if ( empty( $code ) ) {
            return $this->error( __( 'Authorization code is required.', 'profit-per-post' ) );
        }

        $result = $this->integration_manager->handle_oauth_callback( $source, $code );

        if ( $result['success'] ) {
            return $this->success( $result );
        }

        return $this->error( $result['message'] );
    }
}
