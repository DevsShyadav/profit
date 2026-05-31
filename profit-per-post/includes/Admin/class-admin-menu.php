<?php
/**
 * Admin Menu - registers WordPress admin menu pages.
 *
 * @package ProfitPerPost\Admin
 */

namespace ProfitPerPost\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminMenu
 *
 * Registers the plugin's admin menu and submenu pages.
 */
class AdminMenu {

    /**
     * Main menu slug.
     *
     * @var string
     */
    const MENU_SLUG = 'profit-per-post';

    /**
     * Initialize menu hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
    }

    /**
     * Register admin menu pages.
     *
     * @return void
     */
    public function register_menus() {
        // Main menu page.
        add_menu_page(
            __( 'Profit Per Post', 'profit-per-post' ),
            __( 'Profit Per Post', 'profit-per-post' ),
            'ppp_view_revenue',
            self::MENU_SLUG,
            array( $this, 'render_app' ),
            'dashicons-chart-area',
            30
        );

        // Dashboard submenu (same as main).
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'profit-per-post' ),
            __( 'Dashboard', 'profit-per-post' ),
            'ppp_view_revenue',
            self::MENU_SLUG,
            array( $this, 'render_app' )
        );

        // All Posts submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'All Posts', 'profit-per-post' ),
            __( 'All Posts', 'profit-per-post' ),
            'ppp_view_revenue',
            self::MENU_SLUG . '-posts',
            array( $this, 'render_app' )
        );

        // Settings submenu.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'profit-per-post' ),
            __( 'Settings', 'profit-per-post' ),
            'ppp_manage_settings',
            self::MENU_SLUG . '-settings',
            array( $this, 'render_app' )
        );
    }

    /**
     * Render the React app container.
     * All routing is handled by the React SPA.
     *
     * @return void
     */
    public function render_app() {
        // Include the admin app template.
        include PPP_PLUGIN_DIR . 'templates/admin-app.php';
    }

    /**
     * Get current admin page slug.
     *
     * @return string
     */
    public static function get_current_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    }

    /**
     * Check if we are on a plugin admin page.
     *
     * @return bool
     */
    public static function is_plugin_page() {
        $page = self::get_current_page();
        return 0 === strpos( $page, self::MENU_SLUG );
    }
}
