<?php
/**
 * Settings Endpoint - plugin settings CRUD.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Security\CapabilityManager;
use ProfitPerPost\Security\Sanitizer;
use ProfitPerPost\Sync\SyncScheduler;
use ProfitPerPost\Revenue\AttributionModel;
use ProfitPerPost\Utilities\CurrencyFormatter;
use ProfitPerPost\Admin\Onboarding;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SettingsEndpoint
 *
 * REST endpoints for reading and updating plugin settings.
 */
class SettingsEndpoint extends RestController {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_settings' ),
                'permission_callback' => array( CapabilityManager::class, 'rest_permission_admin' ),
            ),
        ));

        register_rest_route( $this->namespace, '/settings/onboarding', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_onboarding_status' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
        ));

        register_rest_route( $this->namespace, '/settings/onboarding/complete', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'complete_onboarding' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_admin' ),
        ));
    }

    /**
     * Get all settings.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_settings( $request ) {
        $settings = array(
            'sync_frequency'       => get_option( 'ppp_sync_frequency', 'every_six_hours' ),
            'currency'             => get_option( 'ppp_currency', 'USD' ),
            'date_format'          => get_option( 'ppp_date_format', 'M j, Y' ),
            'posts_per_page'       => (int) get_option( 'ppp_posts_per_page', 20 ),
            'default_date_range'   => get_option( 'ppp_default_date_range', '30' ),
            'show_revenue_column'  => (bool) get_option( 'ppp_show_revenue_column', true ),
            'debug_mode'           => (bool) get_option( 'ppp_debug_mode', false ),
            'data_retention_days'  => (int) get_option( 'ppp_data_retention_days', 365 ),
            'affiliate_rev_per_click' => (float) get_option( 'ppp_affiliate_rev_per_click', 0.50 ),
            'attribution_model'    => get_option( 'ppp_attribution_model', 'last_touch' ),
            'wc_cookie_days'       => (int) get_option( 'ppp_wc_cookie_days', 30 ),
            'enabled_sources'      => get_option( 'ppp_enabled_sources', array() ),
            'affiliate_patterns'   => get_option( 'ppp_affiliate_patterns', array() ),
            'onboarding_complete'  => (bool) get_option( 'ppp_onboarding_complete', false ),
        );

        // Add available options for dropdowns.
        $settings['_options'] = array(
            'currencies'         => CurrencyFormatter::get_available_currencies(),
            'sync_frequencies'   => SyncScheduler::get_available_frequencies(),
            'attribution_models' => AttributionModel::get_available_models(),
        );

        return $this->success( $settings );
    }

    /**
     * Update settings.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function update_settings( $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            return $this->error( __( 'No settings provided.', 'profit-per-post' ) );
        }

        $schema = array(
            'sync_frequency'       => 'string',
            'currency'             => 'string',
            'date_format'          => 'string',
            'posts_per_page'       => 'integer',
            'default_date_range'   => 'string',
            'show_revenue_column'  => 'boolean',
            'debug_mode'           => 'boolean',
            'data_retention_days'  => 'integer',
            'affiliate_rev_per_click' => 'float',
            'attribution_model'    => 'string',
            'wc_cookie_days'       => 'integer',
            'affiliate_patterns'   => 'array',
        );

        $sanitized = Sanitizer::sanitize_settings( $params, $schema );

        // Update each setting.
        $updated = array();
        foreach ( $sanitized as $key => $value ) {
            $option_key = 'ppp_' . $key;
            update_option( $option_key, $value );
            $updated[ $key ] = $value;
        }

        // Handle sync frequency change.
        if ( isset( $updated['sync_frequency'] ) ) {
            $scheduler = new SyncScheduler( \ProfitPerPost\Plugin::get_instance()->get_sync_manager() );
            $scheduler->reschedule( $updated['sync_frequency'] );
        }

        return $this->success( array(
            'message' => __( 'Settings updated successfully.', 'profit-per-post' ),
            'updated' => $updated,
        ));
    }

    /**
     * Get onboarding status.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_onboarding_status( $request ) {
        return $this->success( Onboarding::get_progress() );
    }

    /**
     * Complete onboarding.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function complete_onboarding( $request ) {
        update_option( 'ppp_onboarding_complete', true );

        return $this->success( array(
            'message' => __( 'Onboarding completed.', 'profit-per-post' ),
        ));
    }
}
