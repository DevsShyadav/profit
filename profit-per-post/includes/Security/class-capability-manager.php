<?php
/**
 * Capability Manager - Role-based access control.
 *
 * @package ProfitPerPost\Security
 */

namespace ProfitPerPost\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CapabilityManager
 *
 * Manages custom capabilities and permission checks for the plugin.
 */
class CapabilityManager {

    /**
     * Custom capability for viewing revenue data.
     *
     * @var string
     */
    const CAP_VIEW_REVENUE = 'ppp_view_revenue';

    /**
     * Custom capability for managing settings.
     *
     * @var string
     */
    const CAP_MANAGE_SETTINGS = 'ppp_manage_settings';

    /**
     * Custom capability for managing connections.
     *
     * @var string
     */
    const CAP_MANAGE_CONNECTIONS = 'ppp_manage_connections';

    /**
     * Custom capability for exporting data.
     *
     * @var string
     */
    const CAP_EXPORT_DATA = 'ppp_export_data';

    /**
     * Custom capability for triggering syncs.
     *
     * @var string
     */
    const CAP_TRIGGER_SYNC = 'ppp_trigger_sync';

    /**
     * Register custom capabilities with roles.
     *
     * @return void
     */
    public static function register_capabilities() {
        $admin_role = get_role( 'administrator' );

        if ( ! $admin_role ) {
            return;
        }

        $capabilities = self::get_all_capabilities();

        foreach ( $capabilities as $cap ) {
            if ( ! $admin_role->has_cap( $cap ) ) {
                $admin_role->add_cap( $cap );
            }
        }

        // Also grant view capability to editors.
        $editor_role = get_role( 'editor' );
        if ( $editor_role ) {
            if ( ! $editor_role->has_cap( self::CAP_VIEW_REVENUE ) ) {
                $editor_role->add_cap( self::CAP_VIEW_REVENUE );
            }
            if ( ! $editor_role->has_cap( self::CAP_EXPORT_DATA ) ) {
                $editor_role->add_cap( self::CAP_EXPORT_DATA );
            }
        }
    }

    /**
     * Remove custom capabilities from roles.
     *
     * @return void
     */
    public static function remove_capabilities() {
        $capabilities = self::get_all_capabilities();
        $roles        = array( 'administrator', 'editor', 'author' );

        foreach ( $roles as $role_name ) {
            $role = get_role( $role_name );
            if ( $role ) {
                foreach ( $capabilities as $cap ) {
                    $role->remove_cap( $cap );
                }
            }
        }
    }

    /**
     * Get all custom capabilities.
     *
     * @return array
     */
    public static function get_all_capabilities() {
        return array(
            self::CAP_VIEW_REVENUE,
            self::CAP_MANAGE_SETTINGS,
            self::CAP_MANAGE_CONNECTIONS,
            self::CAP_EXPORT_DATA,
            self::CAP_TRIGGER_SYNC,
        );
    }

    /**
     * Check if current user can view revenue data.
     *
     * @return bool
     */
    public static function can_view_revenue() {
        return current_user_can( self::CAP_VIEW_REVENUE ) || current_user_can( 'manage_options' );
    }

    /**
     * Check if current user can manage settings.
     *
     * @return bool
     */
    public static function can_manage_settings() {
        return current_user_can( self::CAP_MANAGE_SETTINGS ) || current_user_can( 'manage_options' );
    }

    /**
     * Check if current user can manage connections.
     *
     * @return bool
     */
    public static function can_manage_connections() {
        return current_user_can( self::CAP_MANAGE_CONNECTIONS ) || current_user_can( 'manage_options' );
    }

    /**
     * Check if current user can export data.
     *
     * @return bool
     */
    public static function can_export_data() {
        return current_user_can( self::CAP_EXPORT_DATA ) || current_user_can( 'manage_options' );
    }

    /**
     * Check if current user can trigger sync.
     *
     * @return bool
     */
    public static function can_trigger_sync() {
        return current_user_can( self::CAP_TRIGGER_SYNC ) || current_user_can( 'manage_options' );
    }

    /**
     * Verify a WordPress nonce.
     *
     * @param string $nonce  The nonce value.
     * @param string $action The nonce action.
     * @return bool
     */
    public static function verify_nonce( $nonce, $action = 'ppp_rest' ) {
        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Check REST API permission callback for viewing data.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error
     */
    public static function rest_permission_view( $request ) {
        if ( ! self::can_view_revenue() ) {
            return new \WP_Error(
                'ppp_forbidden',
                __( 'You do not have permission to view revenue data.', 'profit-per-post' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Check REST API permission callback for admin actions.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error
     */
    public static function rest_permission_admin( $request ) {
        if ( ! self::can_manage_settings() ) {
            return new \WP_Error(
                'ppp_forbidden',
                __( 'You do not have permission to manage settings.', 'profit-per-post' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Check REST API permission callback for connections.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error
     */
    public static function rest_permission_connections( $request ) {
        if ( ! self::can_manage_connections() ) {
            return new \WP_Error(
                'ppp_forbidden',
                __( 'You do not have permission to manage connections.', 'profit-per-post' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Check REST API permission callback for sync operations.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error
     */
    public static function rest_permission_sync( $request ) {
        if ( ! self::can_trigger_sync() ) {
            return new \WP_Error(
                'ppp_forbidden',
                __( 'You do not have permission to trigger sync operations.', 'profit-per-post' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Check REST API permission callback for export.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error
     */
    public static function rest_permission_export( $request ) {
        if ( ! self::can_export_data() ) {
            return new \WP_Error(
                'ppp_forbidden',
                __( 'You do not have permission to export data.', 'profit-per-post' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }
}
