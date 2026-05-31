<?php
/**
 * Sync Endpoint - manual sync and status.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Sync\SyncManager;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SyncEndpoint
 *
 * REST endpoints for triggering manual sync and
 * checking sync status/history.
 */
class SyncEndpoint extends RestController {

    /**
     * Sync manager.
     *
     * @var SyncManager
     */
    private $sync_manager;

    /**
     * Constructor.
     *
     * @param SyncManager $sync_manager Sync manager.
     */
    public function __construct( SyncManager $sync_manager ) {
        $this->sync_manager = $sync_manager;
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        // Get sync status.
        register_rest_route( $this->namespace, '/sync/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_status' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
        ));

        // Trigger sync all.
        register_rest_route( $this->namespace, '/sync/trigger', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'trigger_sync' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_sync' ),
            'args'                => array(
                'source' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'start_date' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Get sync history.
        register_rest_route( $this->namespace, '/sync/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            'args'                => array(
                'limit' => array(
                    'type'    => 'integer',
                    'default' => 50,
                    'sanitize_callback' => 'absint',
                ),
                'source' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Get sync status for all sources.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_status( $request ) {
        $status = $this->sync_manager->get_sync_status();

        $next_sync = wp_next_scheduled( 'ppp_sync_all_sources' );

        return $this->success( array(
            'sources'    => $status,
            'next_sync'  => $next_sync ? gmdate( 'Y-m-d H:i:s', $next_sync ) : null,
            'is_syncing' => $this->sync_manager->is_any_syncing(),
        ));
    }

    /**
     * Trigger a manual sync.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function trigger_sync( $request ) {
        $source     = $request->get_param( 'source' );
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        if ( $this->sync_manager->is_any_syncing() ) {
            return $this->error( __( 'A sync is already in progress. Please wait.', 'profit-per-post' ) );
        }

        if ( $source ) {
            // Sync single source.
            $result = $this->sync_manager->sync_source( $source, $start_date, $end_date );

            return $result['success']
                ? $this->success( $result )
                : $this->error( $result['message'] );
        }

        // Sync all sources.
        $results = $this->sync_manager->sync_all( $start_date, $end_date );

        return $this->success( array(
            'message' => __( 'Sync completed.', 'profit-per-post' ),
            'results' => $results,
        ));
    }

    /**
     * Get sync history.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_history( $request ) {
        $limit  = $request->get_param( 'limit' ) ?: 50;
        $source = $request->get_param( 'source' );

        $history = $this->sync_manager->get_sync_history( $limit, $source ?: null );

        return $this->success( $history );
    }
}
