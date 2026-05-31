<?php
/**
 * Admin Notices - display admin notifications.
 *
 * @package ProfitPerPost\Admin
 */

namespace ProfitPerPost\Admin;

use ProfitPerPost\Database\Migrator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminNotices
 *
 * Manages admin notices for setup reminders,
 * connection issues, and sync errors.
 */
class AdminNotices {

    /**
     * Initialize notice hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
        add_action( 'wp_ajax_ppp_dismiss_notice', array( $this, 'dismiss_notice' ) );
    }

    /**
     * Display relevant admin notices.
     *
     * @return void
     */
    public function display_notices() {
        // Only show on our pages or the dashboard.
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Show setup notice if onboarding not complete.
        $this->maybe_show_setup_notice();

        // Show if tables are missing.
        $this->maybe_show_repair_notice();

        // Show connection errors.
        $this->maybe_show_connection_errors();
    }

    /**
     * Show setup notice if onboarding is not complete.
     *
     * @return void
     */
    private function maybe_show_setup_notice() {
        if ( get_option( 'ppp_onboarding_complete', false ) ) {
            return;
        }

        if ( $this->is_dismissed( 'setup_reminder' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $setup_url = admin_url( 'admin.php?page=profit-per-post' );

        printf(
            '<div class="notice notice-info is-dismissible ppp-notice" data-notice-id="setup_reminder">
                <p><strong>%s</strong> %s <a href="%s">%s</a></p>
            </div>',
            esc_html__( 'Profit Per Post:', 'profit-per-post' ),
            esc_html__( 'Welcome! Connect your revenue sources to start tracking earnings per post.', 'profit-per-post' ),
            esc_url( $setup_url ),
            esc_html__( 'Complete Setup', 'profit-per-post' )
        );
    }

    /**
     * Show notice if database tables need repair.
     *
     * @return void
     */
    private function maybe_show_repair_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! AdminMenu::is_plugin_page() ) {
            return;
        }

        if ( ! Migrator::tables_exist() ) {
            printf(
                '<div class="notice notice-error">
                    <p><strong>%s</strong> %s</p>
                </div>',
                esc_html__( 'Profit Per Post:', 'profit-per-post' ),
                esc_html__( 'Database tables are missing. Please deactivate and reactivate the plugin.', 'profit-per-post' )
            );
        }
    }

    /**
     * Show connection error notices.
     *
     * @return void
     */
    private function maybe_show_connection_errors() {
        if ( ! AdminMenu::is_plugin_page() ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check for expired connections.
        global $wpdb;
        $table = \ProfitPerPost\Database\Schema::connections_table();

        $expired = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'expired'"
        );

        if ( $expired > 0 ) {
            $settings_url = admin_url( 'admin.php?page=profit-per-post-settings' );

            printf(
                '<div class="notice notice-warning">
                    <p><strong>%s</strong> %s <a href="%s">%s</a></p>
                </div>',
                esc_html__( 'Profit Per Post:', 'profit-per-post' ),
                sprintf(
                    /* translators: %d: Number of expired connections */
                    esc_html__( '%d connection(s) have expired tokens. Please reconnect them.', 'profit-per-post' ),
                    (int) $expired
                ),
                esc_url( $settings_url ),
                esc_html__( 'Go to Settings', 'profit-per-post' )
            );
        }
    }

    /**
     * AJAX handler to dismiss a notice.
     *
     * @return void
     */
    public function dismiss_notice() {
        check_ajax_referer( 'ppp_dismiss_notice', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        $notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) : '';

        if ( ! empty( $notice_id ) ) {
            $dismissed = get_option( 'ppp_dismissed_notices', array() );
            $dismissed[ $notice_id ] = current_time( 'mysql' );
            update_option( 'ppp_dismissed_notices', $dismissed );
        }

        wp_die( 1 );
    }

    /**
     * Check if a notice has been dismissed.
     *
     * @param string $notice_id The notice identifier.
     * @return bool
     */
    private function is_dismissed( $notice_id ) {
        $dismissed = get_option( 'ppp_dismissed_notices', array() );
        return isset( $dismissed[ $notice_id ] );
    }
}
