<?php
/**
 * Onboarding - setup wizard logic.
 *
 * @package ProfitPerPost\Admin
 */

namespace ProfitPerPost\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Onboarding
 *
 * Manages the onboarding wizard state and redirect logic.
 * The actual wizard UI is rendered by the React frontend.
 */
class Onboarding {

    /**
     * Initialize onboarding hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
        add_action( 'wp_ajax_ppp_complete_onboarding', array( $this, 'complete_onboarding' ) );
        add_action( 'wp_ajax_ppp_skip_onboarding', array( $this, 'skip_onboarding' ) );
    }

    /**
     * Redirect to setup wizard on first activation.
     *
     * @return void
     */
    public function maybe_redirect_to_setup() {
        // Check for activation redirect transient.
        if ( ! get_transient( 'ppp_activation_redirect' ) ) {
            return;
        }

        // Delete transient to prevent repeated redirects.
        delete_transient( 'ppp_activation_redirect' );

        // Don't redirect on multisite bulk activation.
        if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        // Don't redirect if onboarding is already complete.
        if ( get_option( 'ppp_onboarding_complete', false ) ) {
            return;
        }

        // Redirect to the main plugin page (which shows the wizard).
        wp_safe_redirect( admin_url( 'admin.php?page=profit-per-post&ppp_onboarding=1' ) );
        exit;
    }

    /**
     * AJAX: Mark onboarding as complete.
     *
     * @return void
     */
    public function complete_onboarding() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        update_option( 'ppp_onboarding_complete', true );

        wp_send_json_success( array( 'message' => 'Onboarding completed.' ) );
    }

    /**
     * AJAX: Skip onboarding.
     *
     * @return void
     */
    public function skip_onboarding() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        update_option( 'ppp_onboarding_complete', true );

        wp_send_json_success( array( 'message' => 'Onboarding skipped.' ) );
    }

    /**
     * Check if onboarding is complete.
     *
     * @return bool
     */
    public static function is_complete() {
        return (bool) get_option( 'ppp_onboarding_complete', false );
    }

    /**
     * Reset onboarding (for testing).
     *
     * @return void
     */
    public static function reset() {
        update_option( 'ppp_onboarding_complete', false );
        delete_option( 'ppp_dismissed_notices' );
    }

    /**
     * Get onboarding progress.
     *
     * @return array
     */
    public static function get_progress() {
        $enabled_sources = get_option( 'ppp_enabled_sources', array() );

        $steps = array(
            'google_analytics' => ! empty( $enabled_sources['google_analytics'] ),
            'ads'              => ! empty( $enabled_sources['adsense'] ) || ! empty( $enabled_sources['mediavine'] ),
            'woocommerce'      => ! empty( $enabled_sources['woocommerce'] ),
            'affiliate'        => ! empty( $enabled_sources['affiliate'] ),
        );

        $completed_steps = count( array_filter( $steps ) );
        $total_steps     = count( $steps );

        return array(
            'steps'          => $steps,
            'completed'      => $completed_steps,
            'total'          => $total_steps,
            'percentage'     => $total_steps > 0 ? round( ( $completed_steps / $total_steps ) * 100 ) : 0,
            'is_complete'    => self::is_complete(),
        );
    }
}
