<?php
/**
 * Batch Processor - handles large data sets in chunks.
 *
 * @package ProfitPerPost\Sync
 */

namespace ProfitPerPost\Sync;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BatchProcessor
 *
 * Processes large data sets in batches to avoid timeout issues.
 * Used when syncing sites with thousands of posts.
 */
class BatchProcessor {

    /**
     * Default batch size.
     *
     * @var int
     */
    const DEFAULT_BATCH_SIZE = 100;

    /**
     * Maximum execution time per batch (seconds).
     *
     * @var int
     */
    const MAX_EXECUTION_TIME = 25;

    /**
     * Batch size.
     *
     * @var int
     */
    private $batch_size;

    /**
     * Processing callback.
     *
     * @var callable
     */
    private $callback;

    /**
     * Total items to process.
     *
     * @var int
     */
    private $total_items = 0;

    /**
     * Items processed so far.
     *
     * @var int
     */
    private $processed = 0;

    /**
     * Errors encountered.
     *
     * @var array
     */
    private $errors = array();

    /**
     * Start time of current batch.
     *
     * @var float
     */
    private $start_time = 0;

    /**
     * Constructor.
     *
     * @param int $batch_size Number of items to process per batch.
     */
    public function __construct( $batch_size = self::DEFAULT_BATCH_SIZE ) {
        $this->batch_size = max( 1, (int) $batch_size );
    }

    /**
     * Process an array of items in batches.
     *
     * @param array    $items    Array of items to process.
     * @param callable $callback Callback function to process each item. Receives item as argument.
     * @return array Results array with processed count and errors.
     */
    public function process( $items, $callback ) {
        $this->callback    = $callback;
        $this->total_items = count( $items );
        $this->processed   = 0;
        $this->errors      = array();
        $this->start_time  = microtime( true );

        $batches = array_chunk( $items, $this->batch_size );

        foreach ( $batches as $batch_index => $batch ) {
            // Check if we're running out of time.
            if ( $this->is_time_exceeded() ) {
                break;
            }

            $this->process_batch( $batch, $batch_index );
        }

        return $this->get_results();
    }

    /**
     * Process a single batch of items.
     *
     * @param array $batch       The batch items.
     * @param int   $batch_index The batch index.
     * @return void
     */
    private function process_batch( $batch, $batch_index ) {
        foreach ( $batch as $item ) {
            if ( $this->is_time_exceeded() ) {
                break;
            }

            try {
                $result = call_user_func( $this->callback, $item );
                if ( false !== $result ) {
                    $this->processed++;
                }
            } catch ( \Exception $e ) {
                $this->errors[] = array(
                    'item'    => $item,
                    'message' => $e->getMessage(),
                    'batch'   => $batch_index,
                );
            }
        }

        // Free memory between batches.
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }

    /**
     * Check if execution time limit is exceeded.
     *
     * @return bool
     */
    private function is_time_exceeded() {
        $elapsed = microtime( true ) - $this->start_time;
        return $elapsed >= self::MAX_EXECUTION_TIME;
    }

    /**
     * Get processing results.
     *
     * @return array
     */
    public function get_results() {
        $elapsed = microtime( true ) - $this->start_time;

        return array(
            'total_items'    => $this->total_items,
            'processed'      => $this->processed,
            'remaining'      => $this->total_items - $this->processed,
            'errors'         => $this->errors,
            'error_count'    => count( $this->errors ),
            'duration'       => round( $elapsed, 2 ),
            'completed'      => ( $this->processed >= $this->total_items ),
            'time_exceeded'  => $this->is_time_exceeded(),
        );
    }

    /**
     * Process items using a generator for memory efficiency.
     *
     * @param \Generator $generator Items generator.
     * @param callable   $callback  Processing callback.
     * @return array Results.
     */
    public function process_generator( $generator, $callback ) {
        $this->callback   = $callback;
        $this->processed  = 0;
        $this->errors     = array();
        $this->start_time = microtime( true );

        $batch_count = 0;

        foreach ( $generator as $item ) {
            if ( $this->is_time_exceeded() ) {
                break;
            }

            try {
                $result = call_user_func( $this->callback, $item );
                if ( false !== $result ) {
                    $this->processed++;
                }
            } catch ( \Exception $e ) {
                $this->errors[] = array(
                    'item'    => $item,
                    'message' => $e->getMessage(),
                );
            }

            $batch_count++;

            // Flush cache periodically.
            if ( $batch_count % $this->batch_size === 0 ) {
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                }
            }
        }

        $this->total_items = $this->processed + count( $this->errors );

        return $this->get_results();
    }

    /**
     * Set batch size.
     *
     * @param int $size Batch size.
     * @return self
     */
    public function set_batch_size( $size ) {
        $this->batch_size = max( 1, (int) $size );
        return $this;
    }

    /**
     * Get current batch size.
     *
     * @return int
     */
    public function get_batch_size() {
        return $this->batch_size;
    }
}
