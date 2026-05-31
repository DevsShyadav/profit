<?php
/**
 * Plugin Name: Profit Per Post
 * Plugin URI: https://profitperpost.com
 * Description: Shows exact revenue each blog post generates by connecting Google Analytics, AdSense, Mediavine, WooCommerce, and affiliate tracking.
 * Version: 1.0.0
 * Author: Profit Per Post
 * Author URI: https://profitperpost.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: profit-per-post
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package ProfitPerPost
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin version.
define( 'PPP_VERSION', '1.0.0' );

// Plugin file path.
define( 'PPP_PLUGIN_FILE', __FILE__ );

// Plugin directory path.
define( 'PPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Plugin directory URL.
define( 'PPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'PPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Database table prefix for this plugin.
define( 'PPP_TABLE_PREFIX', 'ppp_' );

// Minimum WordPress version.
define( 'PPP_MIN_WP_VERSION', '6.0' );

// Minimum PHP version.
define( 'PPP_MIN_PHP_VERSION', '7.4' );

// REST API namespace.
define( 'PPP_REST_NAMESPACE', 'ppp/v1' );

// Encryption key option name.
define( 'PPP_ENCRYPTION_KEY_OPTION', 'ppp_encryption_key' );

// Debug mode option.
define( 'PPP_DEBUG', get_option( 'ppp_debug_mode', false ) );

/**
 * Check minimum requirements before loading plugin.
 *
 * @return bool
 */
function ppp_check_requirements() {
    $errors = array();

    if ( version_compare( PHP_VERSION, PPP_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            __( 'Profit Per Post requires PHP %1$s or higher. You are running PHP %2$s.', 'profit-per-post' ),
            PPP_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    global $wp_version;
    if ( version_compare( $wp_version, PPP_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required WordPress version, 2: Current WordPress version */
            __( 'Profit Per Post requires WordPress %1$s or higher. You are running WordPress %2$s.', 'profit-per-post' ),
            PPP_MIN_WP_VERSION,
            $wp_version
        );
    }

    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', function() use ( $errors ) {
            foreach ( $errors as $error ) {
                printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
            }
        });
        return false;
    }

    return true;
}

/**
 * Load the autoloader.
 */
require_once PPP_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Initialize the autoloader.
 */
\ProfitPerPost\Autoloader::register();

/**
 * Plugin activation hook.
 */
function ppp_activate() {
    if ( ! ppp_check_requirements() ) {
        deactivate_plugins( PPP_PLUGIN_BASENAME );
        wp_die(
            esc_html__( 'Profit Per Post cannot be activated. Please check system requirements.', 'profit-per-post' ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }
    \ProfitPerPost\Activator::activate();
}
register_activation_hook( __FILE__, 'ppp_activate' );

/**
 * Plugin deactivation hook.
 */
function ppp_deactivate() {
    \ProfitPerPost\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'ppp_deactivate' );

/**
 * Begin execution of the plugin.
 */
function ppp_run() {
    if ( ! ppp_check_requirements() ) {
        return;
    }

    $plugin = \ProfitPerPost\Plugin::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'ppp_run' );
