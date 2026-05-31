<?php
/**
 * Sync Log Model - CRUD operations for sync log data.
 *
 * @package ProfitPerPost\Database
 */

namespace ProfitPerPost\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SyncLogModel
 *
 * Handles all database operations for the sync_log table.
 */
class SyncLogModel {

    /**
     * Start a new sync log entry.
     *
     * @param string $source The integration source name.
     * @return int|false The log ID or false on failure.
     */
    public static function start( $source ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        $result = $wpdb->insert(
            $table,
            array(
                'source'     => $source,
                'status'     => 'started',
                'started_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Mark a sync log entry as completed.
     *
     * @param int $log_id        The log entry ID.
     * @param int $records_synced Number of records synced.
     * @return bool
     */
    public static function complete( $log_id, $records_synced = 0 ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        $started_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT started_at FROM {$table} WHERE id = %d",
                $log_id
            )
        );

        $now      = current_time( 'mysql' );
        $duration = $started_at ? ( strtotime( $now ) - strtotime( $started_at ) ) : 0;

        $result = $wpdb->update(
            $table,
            array(
                'status'           => 'completed',
                'records_synced'   => $records_synced,
                'completed_at'     => $now,
                'duration_seconds' => $duration,
            ),
            array( 'id' => $log_id ),
            array( '%s', '%d', '%s', '%f' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Mark a sync log entry as failed.
     *
     * @param int    $log_id        The log entry ID.
     * @param string $error_message Error message.
     * @return bool
     */
    public static function fail( $log_id, $error_message = '' ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        $started_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT started_at FROM {$table} WHERE id = %d",
                $log_id
            )
        );

        $now      = current_time( 'mysql' );
        $duration = $started_at ? ( strtotime( $now ) - strtotime( $started_at ) ) : 0;

        $result = $wpdb->update(
            $table,
            array(
                'status'           => 'failed',
                'error_message'    => $error_message,
                'completed_at'     => $now,
                'duration_seconds' => $duration,
            ),
            array( 'id' => $log_id ),
            array( '%s', '%s', '%s', '%f' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Get recent sync logs.
     *
     * @param int    $limit  Number of records.
     * @param string $source Optional source filter.
     * @return array
     */
    public static function get_recent( $limit = 50, $source = null ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        if ( $source ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE source = %s
                    ORDER BY started_at DESC
                    LIMIT %d",
                    $source,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    ORDER BY started_at DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }

        return $results ? $results : array();
    }

    /**
     * Get last sync status for a source.
     *
     * @param string $source The source identifier.
     * @return array|null
     */
    public static function get_last_sync( $source ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE source = %s
                ORDER BY started_at DESC
                LIMIT 1",
                $source
            ),
            ARRAY_A
        );

        return $result ? $result : null;
    }

    /**
     * Get sync status summary for all sources.
     *
     * @return array
     */
    public static function get_all_sources_status() {
        global $wpdb;
        $table = Schema::sync_log_table();

        $results = $wpdb->get_results(
            "SELECT source,
            MAX(started_at) as last_sync,
            (SELECT status FROM {$table} t2 WHERE t2.source = t1.source ORDER BY started_at DESC LIMIT 1) as last_status
            FROM {$table} t1
            GROUP BY source
            ORDER BY source",
            ARRAY_A
        );

        return $results ? $results : array();
    }

    /**
     * Check if a sync is currently running for a source.
     *
     * @param string $source The source identifier.
     * @return bool
     */
    public static function is_syncing( $source ) {
        global $wpdb;
        $table = Schema::sync_log_table();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE source = %s AND status = 'started'
                AND started_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
                $source
            )
        );

        return (int) $result > 0;
    }

    /**
     * Clean up stale sync entries (started > 30 min ago without completion).
     *
     * @return int Number of rows updated.
     */
    public static function cleanup_stale() {
        global $wpdb;
        $table = Schema::sync_log_table();

        return $wpdb->query(
            "UPDATE {$table}
            SET status = 'failed', error_message = 'Sync timed out', completed_at = NOW()
            WHERE status = 'started'
            AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
    }

    /**
     * Delete old log entries.
     *
     * @param int $days Number of days to retain.
     * @return int Number of rows deleted.
     */
    public static function prune_old_logs( $days = 90 ) {
        global $wpdb;
        $table  = Schema::sync_log_table();
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE started_at < %s",
                $cutoff
            )
        );
    }
}
