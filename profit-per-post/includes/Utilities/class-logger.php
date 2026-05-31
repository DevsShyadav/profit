<?php
/**
 * Logger - plugin logging system.
 *
 * @package ProfitPerPost\Utilities
 */

namespace ProfitPerPost\Utilities;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Logger
 *
 * Provides structured logging for debugging and monitoring.
 * Logs are written to a file when debug mode is enabled.
 */
class Logger {

    /**
     * Log levels.
     */
    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';

    /**
     * Log file path.
     *
     * @var string
     */
    private static $log_file = '';

    /**
     * Maximum log file size in bytes (5MB).
     *
     * @var int
     */
    const MAX_FILE_SIZE = 5242880;

    /**
     * Get the log file path.
     *
     * @return string
     */
    private static function get_log_file() {
        if ( empty( self::$log_file ) ) {
            $upload_dir    = wp_upload_dir();
            self::$log_file = $upload_dir['basedir'] . '/profit-per-post-logs/ppp-debug.log';
        }
        return self::$log_file;
    }

    /**
     * Check if logging is enabled.
     *
     * @return bool
     */
    private static function is_enabled() {
        return (bool) get_option( 'ppp_debug_mode', false );
    }

    /**
     * Log a debug message.
     *
     * @param string $message The message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function debug( $message, $context = array() ) {
        self::log( self::LEVEL_DEBUG, $message, $context );
    }

    /**
     * Log an info message.
     *
     * @param string $message The message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function info( $message, $context = array() ) {
        self::log( self::LEVEL_INFO, $message, $context );
    }

    /**
     * Log a warning message.
     *
     * @param string $message The message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function warning( $message, $context = array() ) {
        self::log( self::LEVEL_WARNING, $message, $context );
    }

    /**
     * Log an error message.
     *
     * @param string $message The message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function error( $message, $context = array() ) {
        self::log( self::LEVEL_ERROR, $message, $context );
    }

    /**
     * Write a log entry.
     *
     * @param string $level   Log level.
     * @param string $message The message.
     * @param array  $context Additional context.
     * @return void
     */
    private static function log( $level, $message, $context = array() ) {
        if ( ! self::is_enabled() && self::LEVEL_ERROR !== $level ) {
            return;
        }

        $log_file = self::get_log_file();

        // Ensure directory exists.
        $log_dir = dirname( $log_file );
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            // Add .htaccess to protect log files.
            file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
            // Add index.php for extra security.
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
        }

        // Rotate log if too large.
        if ( file_exists( $log_file ) && filesize( $log_file ) > self::MAX_FILE_SIZE ) {
            self::rotate_log();
        }

        // Format the log entry.
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $entry     = "[{$timestamp}] [{$level}] {$message}";

        if ( ! empty( $context ) ) {
            // Redact sensitive data.
            $context = self::redact_sensitive( $context );
            $entry  .= ' | Context: ' . wp_json_encode( $context );
        }

        $entry .= PHP_EOL;

        // Write to file.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Rotate the log file.
     *
     * @return void
     */
    private static function rotate_log() {
        $log_file = self::get_log_file();
        $backup   = $log_file . '.' . gmdate( 'Y-m-d-His' ) . '.bak';

        if ( file_exists( $log_file ) ) {
            rename( $log_file, $backup );
        }

        // Clean up old backups (keep last 3).
        $log_dir = dirname( $log_file );
        $backups = glob( $log_dir . '/*.bak' );

        if ( $backups && count( $backups ) > 3 ) {
            sort( $backups );
            $to_delete = array_slice( $backups, 0, count( $backups ) - 3 );
            foreach ( $to_delete as $old_backup ) {
                wp_delete_file( $old_backup );
            }
        }
    }

    /**
     * Redact sensitive data from context arrays.
     *
     * @param array $data The data to redact.
     * @return array Redacted data.
     */
    private static function redact_sensitive( $data ) {
        $sensitive_keys = array(
            'access_token', 'refresh_token', 'client_secret',
            'api_key', 'password', 'secret', 'token',
            'credentials', 'authorization',
        );

        foreach ( $data as $key => $value ) {
            $key_lower = strtolower( $key );

            foreach ( $sensitive_keys as $sensitive ) {
                if ( false !== strpos( $key_lower, $sensitive ) ) {
                    $data[ $key ] = '[REDACTED]';
                    break;
                }
            }

            if ( is_array( $value ) ) {
                $data[ $key ] = self::redact_sensitive( $value );
            }
        }

        return $data;
    }

    /**
     * Get recent log entries.
     *
     * @param int $lines Number of lines to return.
     * @return array Array of log lines.
     */
    public static function get_recent_entries( $lines = 100 ) {
        $log_file = self::get_log_file();

        if ( ! file_exists( $log_file ) ) {
            return array();
        }

        $file_content = file_get_contents( $log_file );
        if ( empty( $file_content ) ) {
            return array();
        }

        $all_lines = explode( PHP_EOL, trim( $file_content ) );
        $total     = count( $all_lines );

        if ( $total <= $lines ) {
            return $all_lines;
        }

        return array_slice( $all_lines, $total - $lines );
    }

    /**
     * Clear the log file.
     *
     * @return bool
     */
    public static function clear() {
        $log_file = self::get_log_file();

        if ( file_exists( $log_file ) ) {
            return file_put_contents( $log_file, '' ) !== false;
        }

        return true;
    }

    /**
     * Get log file size in human-readable format.
     *
     * @return string
     */
    public static function get_file_size() {
        $log_file = self::get_log_file();

        if ( ! file_exists( $log_file ) ) {
            return '0 B';
        }

        return size_format( filesize( $log_file ) );
    }
}
