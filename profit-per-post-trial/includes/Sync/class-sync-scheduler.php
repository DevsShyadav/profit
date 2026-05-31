<?php
/**
 * Sync Scheduler - manages WP-Cron scheduling.
 *
 * @package ProfitPerPost\Sync
 */

namespace ProfitPerPost\Sync;

use ProfitPerPost\Database\RevenueModel;
use ProfitPerPost\Database\TrafficModel;
use ProfitPerPost\Database\AffiliateModel;
use ProfitPerPost\Database\SyncLogModel;
use ProfitPerPost\Database\CacheModel;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SyncScheduler
 *
 * Handles WP-Cron event registration and execution
 * for automatic background data synchronization.
 */
class SyncScheduler {

    /**
     * Sync manager instance.
     *
     * @var SyncManager
     */
    private $sync_manager;

    /**
     * Constructor.
     *
     * @param SyncManager $sync_manager Sync manager instance.
     */
    public function __construct( SyncManager $sync_manager ) {
        $this->sync_manager = $sync_manager;
    }

    /**
     * Initialize scheduler hooks.
     *
     * @return void
     */
    public function init() {
        // Register custom cron schedules.
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

        // Hook into cron events.
        add_action( 'ppp_sync_all_sources', array( $this, 'handle_sync_all' ) );
        add_action( 'ppp_cleanup_cache', array( $this, 'handle_cache_cleanup' ) );
        add_action( 'ppp_prune_data', array( $this, 'handle_data_prune' ) );

        // Ensure events are scheduled.
        $this->ensure_scheduled();
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['every_hour'] ) ) {
            $schedules['every_hour'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => __( 'Every Hour', 'profit-per-post' ),
            );
        }

        if ( ! isset( $schedules['every_six_hours'] ) ) {
            $schedules['every_six_hours'] = array(
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => __( 'Every 6 Hours', 'profit-per-post' ),
            );
        }

        if ( ! isset( $schedules['every_twelve_hours'] ) ) {
            $schedules['every_twelve_hours'] = array(
                'interval' => 12 * HOUR_IN_SECONDS,
                'display'  => __( 'Every 12 Hours', 'profit-per-post' ),
            );
        }

        return $schedules;
    }

    /**
     * Ensure scheduled events are registered.
     *
     * @return void
     */
    private function ensure_scheduled() {
        if ( ! wp_next_scheduled( 'ppp_sync_all_sources' ) ) {
            $frequency = get_option( 'ppp_sync_frequency', 'every_six_hours' );
            wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, 'ppp_sync_all_sources' );
        }

        if ( ! wp_next_scheduled( 'ppp_cleanup_cache' ) ) {
            wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'ppp_cleanup_cache' );
        }

        if ( ! wp_next_scheduled( 'ppp_prune_data' ) ) {
            wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'weekly', 'ppp_prune_data' );
        }
    }

    /**
     * Handle the sync all cron event.
     *
     * @return void
     */
    public function handle_sync_all() {
        // Only run if not already syncing.
        if ( $this->sync_manager->is_any_syncing() ) {
            return;
        }

        $this->sync_manager->sync_all();
    }

    /**
     * Handle cache cleanup cron event.
     *
     * @return void
     */
    public function handle_cache_cleanup() {
        CacheModel::clear_expired();
    }

    /**
     * Handle data pruning cron event.
     *
     * @return void
     */
    public function handle_data_prune() {
        $retention_days = (int) get_option( 'ppp_data_retention_days', 365 );

        if ( $retention_days <= 0 ) {
            return; // 0 means keep forever.
        }

        RevenueModel::prune_old_data( $retention_days );
        TrafficModel::prune_old_data( $retention_days );
        AffiliateModel::prune_old_data( $retention_days );
        SyncLogModel::prune_old_logs( 90 ); // Always prune logs older than 90 days.
    }

    /**
     * Reschedule sync with a new frequency.
     *
     * @param string $frequency The new frequency (every_hour, every_six_hours, every_twelve_hours, daily).
     * @return void
     */
    public function reschedule( $frequency ) {
        // Clear existing schedule.
        $timestamp = wp_next_scheduled( 'ppp_sync_all_sources' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ppp_sync_all_sources' );
        }
        wp_clear_scheduled_hook( 'ppp_sync_all_sources' );

        // Schedule with new frequency.
        wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, 'ppp_sync_all_sources' );

        // Save the setting.
        update_option( 'ppp_sync_frequency', $frequency );
    }

    /**
     * Get next scheduled sync time.
     *
     * @return int|false Timestamp of next scheduled sync or false.
     */
    public function get_next_sync_time() {
        return wp_next_scheduled( 'ppp_sync_all_sources' );
    }

    /**
     * Get available sync frequencies.
     *
     * @return array
     */
    public static function get_available_frequencies() {
        return array(
            'every_hour'         => __( 'Every Hour', 'profit-per-post' ),
            'every_six_hours'    => __( 'Every 6 Hours', 'profit-per-post' ),
            'every_twelve_hours' => __( 'Every 12 Hours', 'profit-per-post' ),
            'daily'              => __( 'Once Daily', 'profit-per-post' ),
        );
    }
}
