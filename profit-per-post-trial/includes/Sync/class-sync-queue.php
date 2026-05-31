<?php
/**
 * Sync Queue - priority queue for sync jobs.
 *
 * @package ProfitPerPost\Sync
 */

namespace ProfitPerPost\Sync;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SyncQueue
 *
 * Priority-based queue for managing sync jobs.
 * Ensures high-priority sources are synced first
 * and provides rate limiting capabilities.
 */
class SyncQueue {

    /**
     * Queue option name in wp_options.
     *
     * @var string
     */
    const QUEUE_OPTION = 'ppp_sync_queue';

    /**
     * Priority: High (sync first).
     *
     * @var int
     */
    const PRIORITY_HIGH = 1;

    /**
     * Priority: Normal.
     *
     * @var int
     */
    const PRIORITY_NORMAL = 5;

    /**
     * Priority: Low (sync last).
     *
     * @var int
     */
    const PRIORITY_LOW = 10;

    /**
     * Add a job to the queue.
     *
     * @param string $source_id  Source identifier.
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @param int    $priority   Job priority (lower = higher priority).
     * @return bool
     */
    public static function add( $source_id, $start_date, $end_date, $priority = self::PRIORITY_NORMAL ) {
        $queue = self::get_queue();

        $job = array(
            'id'         => wp_generate_uuid4(),
            'source_id'  => $source_id,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'priority'   => $priority,
            'status'     => 'pending',
            'created_at' => current_time( 'mysql' ),
            'attempts'   => 0,
        );

        $queue[] = $job;

        // Sort by priority.
        usort( $queue, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        });

        return update_option( self::QUEUE_OPTION, $queue );
    }

    /**
     * Get the next pending job from the queue.
     *
     * @return array|null The next job or null if queue is empty.
     */
    public static function get_next() {
        $queue = self::get_queue();

        foreach ( $queue as &$job ) {
            if ( 'pending' === $job['status'] ) {
                $job['status'] = 'processing';
                $job['attempts']++;
                update_option( self::QUEUE_OPTION, $queue );
                return $job;
            }
        }

        return null;
    }

    /**
     * Mark a job as completed.
     *
     * @param string $job_id The job ID.
     * @return bool
     */
    public static function complete( $job_id ) {
        $queue = self::get_queue();

        foreach ( $queue as $index => $job ) {
            if ( $job['id'] === $job_id ) {
                unset( $queue[ $index ] );
                return update_option( self::QUEUE_OPTION, array_values( $queue ) );
            }
        }

        return false;
    }

    /**
     * Mark a job as failed (will be retried).
     *
     * @param string $job_id        The job ID.
     * @param string $error_message Error message.
     * @param int    $max_attempts  Maximum retry attempts.
     * @return bool
     */
    public static function fail( $job_id, $error_message = '', $max_attempts = 3 ) {
        $queue = self::get_queue();

        foreach ( $queue as &$job ) {
            if ( $job['id'] === $job_id ) {
                if ( $job['attempts'] >= $max_attempts ) {
                    // Remove from queue after max attempts.
                    $job['status'] = 'failed';
                    $job['error']  = $error_message;
                } else {
                    // Reset to pending for retry.
                    $job['status']   = 'pending';
                    $job['error']    = $error_message;
                    $job['priority'] = self::PRIORITY_LOW; // Lower priority on retry.
                }
                return update_option( self::QUEUE_OPTION, $queue );
            }
        }

        return false;
    }

    /**
     * Get the current queue.
     *
     * @return array
     */
    public static function get_queue() {
        $queue = get_option( self::QUEUE_OPTION, array() );
        return is_array( $queue ) ? $queue : array();
    }

    /**
     * Get queue size (pending jobs only).
     *
     * @return int
     */
    public static function get_pending_count() {
        $queue = self::get_queue();
        $count = 0;

        foreach ( $queue as $job ) {
            if ( 'pending' === $job['status'] ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear all jobs from the queue.
     *
     * @return bool
     */
    public static function clear() {
        return update_option( self::QUEUE_OPTION, array() );
    }

    /**
     * Remove failed jobs from the queue.
     *
     * @return bool
     */
    public static function clear_failed() {
        $queue    = self::get_queue();
        $filtered = array_filter( $queue, function( $job ) {
            return 'failed' !== $job['status'];
        });

        return update_option( self::QUEUE_OPTION, array_values( $filtered ) );
    }

    /**
     * Check if a source already has a pending job.
     *
     * @param string $source_id The source identifier.
     * @return bool
     */
    public static function has_pending_job( $source_id ) {
        $queue = self::get_queue();

        foreach ( $queue as $job ) {
            if ( $job['source_id'] === $source_id && in_array( $job['status'], array( 'pending', 'processing' ), true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get queue status summary.
     *
     * @return array
     */
    public static function get_status() {
        $queue = self::get_queue();

        $status = array(
            'total'      => count( $queue ),
            'pending'    => 0,
            'processing' => 0,
            'failed'     => 0,
        );

        foreach ( $queue as $job ) {
            if ( isset( $status[ $job['status'] ] ) ) {
                $status[ $job['status'] ]++;
            }
        }

        return $status;
    }
}
