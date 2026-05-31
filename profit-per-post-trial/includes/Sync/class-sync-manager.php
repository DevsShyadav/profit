<?php
/**
 * Sync Manager - orchestrates all sync operations.
 *
 * @package ProfitPerPost\Sync
 */

namespace ProfitPerPost\Sync;

use ProfitPerPost\Integrations\IntegrationManager;
use ProfitPerPost\Cache\CacheManager;
use ProfitPerPost\Database\SyncLogModel;
use ProfitPerPost\Database\CacheModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SyncManager
 *
 * Orchestrates data synchronization from all connected integrations.
 * Manages sync lifecycle, logging, error handling, and cache invalidation.
 */
class SyncManager {

    /**
     * Integration manager instance.
     *
     * @var IntegrationManager
     */
    private $integration_manager;

    /**
     * Cache manager instance.
     *
     * @var CacheManager
     */
    private $cache_manager;

    /**
     * Constructor.
     *
     * @param IntegrationManager $integration_manager Integration manager.
     * @param CacheManager       $cache_manager       Cache manager.
     */
    public function __construct( IntegrationManager $integration_manager, CacheManager $cache_manager ) {
        $this->integration_manager = $integration_manager;
        $this->cache_manager       = $cache_manager;
    }

    /**
     * Sync all connected sources.
     *
     * @param string|null $start_date Start date (Y-m-d). Default: 7 days ago.
     * @param string|null $end_date   End date (Y-m-d). Default: today.
     * @return array Results from all syncs.
     */
    public function sync_all( $start_date = null, $end_date = null ) {
        // Cleanup stale sync entries.
        SyncLogModel::cleanup_stale();

        // Set default date range.
        if ( ! $start_date ) {
            $start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
        }
        if ( ! $end_date ) {
            $end_date = gmdate( 'Y-m-d' );
        }

        $connected = $this->integration_manager->get_connected();
        $results   = array();

        /**
         * Fires before all sources are synced.
         *
         * @param array  $connected  Connected integrations.
         * @param string $start_date Start date.
         * @param string $end_date   End date.
         */
        do_action( 'ppp_before_sync', $connected, $start_date, $end_date );

        foreach ( $connected as $source_id => $integration ) {
            $results[ $source_id ] = $this->sync_source( $source_id, $start_date, $end_date );
        }

        // Invalidate dashboard cache for the synced period.
        $this->cache_manager->invalidate_period( $start_date, $end_date );

        /**
         * Fires after all sources are synced.
         *
         * @param array  $results    Sync results.
         * @param string $start_date Start date.
         * @param string $end_date   End date.
         */
        do_action( 'ppp_after_sync', $results, $start_date, $end_date );

        return $results;
    }

    /**
     * Sync a specific source.
     *
     * @param string $source_id  The source identifier.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return array Result array.
     */
    public function sync_source( $source_id, $start_date = null, $end_date = null ) {
        $integration = $this->integration_manager->get_integration( $source_id );

        if ( ! $integration ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Integration not found.', 'profit-per-post' ),
            );
        }

        if ( ! $integration->is_connected() ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Integration not connected.', 'profit-per-post' ),
            );
        }

        // Check if already syncing.
        if ( SyncLogModel::is_syncing( $source_id ) ) {
            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => __( 'Sync already in progress for this source.', 'profit-per-post' ),
            );
        }

        // Set default date range.
        if ( ! $start_date ) {
            $start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
        }
        if ( ! $end_date ) {
            $end_date = gmdate( 'Y-m-d' );
        }

        // Start sync log.
        $log_id = SyncLogModel::start( $source_id );

        try {
            // Execute sync.
            $result = $integration->sync( $start_date, $end_date );

            if ( $result['success'] ) {
                SyncLogModel::complete( $log_id, $result['records_synced'] );
            } else {
                SyncLogModel::fail( $log_id, $result['message'] );
            }

            // Fire action hook.
            do_action( 'ppp_revenue_calculated', $source_id, $result );

            return $result;

        } catch ( \Exception $e ) {
            $error_message = $e->getMessage();
            SyncLogModel::fail( $log_id, $error_message );

            return array(
                'success'        => false,
                'records_synced' => 0,
                'message'        => $error_message,
            );
        }
    }

    /**
     * Get sync status for all sources.
     *
     * @return array
     */
    public function get_sync_status() {
        $statuses = SyncLogModel::get_all_sources_status();
        $result   = array();

        foreach ( $this->integration_manager->get_all() as $source_id => $integration ) {
            $is_syncing  = SyncLogModel::is_syncing( $source_id );
            $last_sync   = SyncLogModel::get_last_sync( $source_id );

            $result[ $source_id ] = array(
                'source_id'   => $source_id,
                'source_name' => $integration->get_source_name(),
                'is_connected' => $integration->is_connected(),
                'is_syncing'  => $is_syncing,
                'last_sync'   => $last_sync,
            );
        }

        return $result;
    }

    /**
     * Get recent sync history.
     *
     * @param int    $limit  Number of records.
     * @param string $source Optional source filter.
     * @return array
     */
    public function get_sync_history( $limit = 50, $source = null ) {
        return SyncLogModel::get_recent( $limit, $source );
    }

    /**
     * Check if any sync is currently running.
     *
     * @return bool
     */
    public function is_any_syncing() {
        foreach ( $this->integration_manager->get_connected() as $source_id => $integration ) {
            if ( SyncLogModel::is_syncing( $source_id ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Trigger a full historical sync (first-time or repair).
     *
     * @param int $days Number of days back to sync.
     * @return array Results.
     */
    public function full_sync( $days = 90 ) {
        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $end_date   = gmdate( 'Y-m-d' );

        return $this->sync_all( $start_date, $end_date );
    }
}
