<?php
/**
 * Admin Assets - enqueue scripts and styles.
 *
 * @package ProfitPerPost\Admin
 */

namespace ProfitPerPost\Admin;

use ProfitPerPost\Utilities\DateHelper;
use ProfitPerPost\Utilities\CurrencyFormatter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminAssets
 *
 * Handles enqueueing of admin scripts and styles.
 * Only loads on plugin pages for performance.
 */
class AdminAssets {

    /**
     * Initialize asset hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue admin assets conditionally.
     *
     * @param string $hook_suffix The current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        // Only load on our plugin pages.
        if ( ! $this->is_plugin_page( $hook_suffix ) ) {
            return;
        }

        // Enqueue the main React app bundle.
        wp_enqueue_script(
            'ppp-admin-app',
            PPP_PLUGIN_URL . 'assets/dist/js/profit-per-post-admin.js',
            array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-components' ),
            PPP_VERSION,
            true
        );

        // Enqueue the main stylesheet.
        wp_enqueue_style(
            'ppp-admin-styles',
            PPP_PLUGIN_URL . 'assets/dist/css/profit-per-post-admin.css',
            array( 'wp-components' ),
            PPP_VERSION
        );

        // Localize script with config data.
        wp_localize_script( 'ppp-admin-app', 'pppConfig', $this->get_localized_data() );

        // Set script translations.
        wp_set_script_translations( 'ppp-admin-app', 'profit-per-post', PPP_PLUGIN_DIR . 'languages' );
    }

    /**
     * Get localized data for the frontend app.
     *
     * @return array
     */
    private function get_localized_data() {
        return array(
            'restUrl'       => rest_url( PPP_REST_NAMESPACE ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'adminUrl'      => admin_url(),
            'pluginUrl'     => PPP_PLUGIN_URL,
            'version'       => PPP_VERSION,
            'currency'      => get_option( 'ppp_currency', 'USD' ),
            'currencySymbol' => CurrencyFormatter::get_symbol(),
            'dateFormat'    => get_option( 'ppp_date_format', 'M j, Y' ),
            'defaultRange'  => get_option( 'ppp_default_date_range', '30' ),
            'datePresets'   => DateHelper::get_presets(),
            'currencies'    => CurrencyFormatter::get_available_currencies(),
            'isOnboarded'   => true,
            'currentPage'   => AdminMenu::get_current_page(),
            'capabilities'  => array(
                'canManageSettings'   => current_user_can( 'ppp_manage_settings' ) || current_user_can( 'manage_options' ),
                'canManageConnections' => current_user_can( 'ppp_manage_connections' ) || current_user_can( 'manage_options' ),
                'canExport'           => current_user_can( 'ppp_export_data' ) || current_user_can( 'manage_options' ),
                'canSync'             => current_user_can( 'ppp_trigger_sync' ) || current_user_can( 'manage_options' ),
            ),
        );
    }

    /**
     * Check if current page is a plugin page.
     *
     * @param string $hook_suffix The admin page hook suffix.
     * @return bool
     */
    private function is_plugin_page( $hook_suffix ) {
        $plugin_pages = array(
            'toplevel_page_profit-per-post',
            'profit-per-post_page_profit-per-post-posts',
            'profit-per-post_page_profit-per-post-settings',
        );

        return in_array( $hook_suffix, $plugin_pages, true );
    }
}
