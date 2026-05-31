<?php
/**
 * PSR-4 Autoloader for the Profit Per Post plugin.
 *
 * @package ProfitPerPost
 */

namespace ProfitPerPost;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Autoloader
 *
 * Handles autoloading of plugin classes following PSR-4 conventions
 * adapted for WordPress file naming conventions.
 */
class Autoloader {

    /**
     * Namespace prefix for this autoloader.
     *
     * @var string
     */
    private static $namespace_prefix = 'ProfitPerPost\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    private static $base_dir = '';

    /**
     * Mapping of namespace segments to directory names.
     *
     * @var array
     */
    private static $directory_map = array(
        'Admin'        => 'Admin',
        'API'          => 'API',
        'Integrations' => 'Integrations',
        'Revenue'      => 'Revenue',
        'Database'     => 'Database',
        'Sync'         => 'Sync',
        'Cache'        => 'Cache',
        'Security'     => 'Security',
        'Utilities'    => 'Utilities',
    );

    /**
     * Register the autoloader with SPL.
     *
     * @return void
     */
    public static function register() {
        self::$base_dir = PPP_PLUGIN_DIR . 'includes/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload callback.
     *
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload( $class ) {
        // Check if the class uses our namespace prefix.
        $len = strlen( self::$namespace_prefix );
        if ( strncmp( self::$namespace_prefix, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr( $class, $len );

        // Build the file path.
        $file = self::get_file_path( $relative_class );

        // If the file exists, require it.
        if ( $file && file_exists( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Convert a relative class name to a file path.
     *
     * Follows WordPress naming convention: class-{name}.php
     *
     * @param string $relative_class The relative class name.
     * @return string|false The file path or false if not resolvable.
     */
    private static function get_file_path( $relative_class ) {
        // Split the relative class name into parts.
        $parts = explode( '\\', $relative_class );

        // Get the class name (last part).
        $class_name = array_pop( $parts );

        // Convert class name to file name (WordPress convention).
        $file_name = 'class-' . self::class_to_filename( $class_name ) . '.php';

        // Build the directory path from remaining namespace parts.
        $directory = self::$base_dir;

        if ( ! empty( $parts ) ) {
            foreach ( $parts as $part ) {
                if ( isset( self::$directory_map[ $part ] ) ) {
                    $directory .= self::$directory_map[ $part ] . '/';
                } else {
                    $directory .= $part . '/';
                }
            }
        }

        return $directory . $file_name;
    }

    /**
     * Convert a CamelCase class name to a WordPress-style filename.
     *
     * Example: RevenuCalculator -> revenue-calculator
     *          GoogleAnalytics -> google-analytics
     *          REST_Controller -> rest-controller
     *
     * @param string $class_name The class name.
     * @return string The converted filename (without extension).
     */
    private static function class_to_filename( $class_name ) {
        // Handle underscores (REST_Controller -> REST-Controller).
        $class_name = str_replace( '_', '-', $class_name );

        // Insert hyphens before uppercase letters.
        $filename = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name );
        $filename = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $filename );

        // Convert to lowercase.
        $filename = strtolower( $filename );

        return $filename;
    }
}
