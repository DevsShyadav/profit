<?php
/**
 * Integration Manager - registry and dispatcher for all integrations.
 *
 * @package ProfitPerPost\Integrations
 */

namespace ProfitPerPost\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IntegrationManager
 *
 * Manages all revenue source integrations. Acts as a registry
 * and dispatcher for sync operations.
 */
class IntegrationManager {

    /**
     * Registered integrations.
     *
     * @var array<string, BaseIntegration>
     */
    private $integrations = array();

    /**
     * Constructor - registers all built-in integrations.
     */
    public function __construct() {
        $this->register_built_in_integrations();

        /**
         * Allow third-party plugins to register additional integrations.
         *
         * @param IntegrationManager $this The integration manager instance.
         */
        do_action( 'ppp_sources_registered', $this );
    }

    /**
     * Register all built-in integrations.
     *
     * @return void
     */
    private function register_built_in_integrations() {
        $this->register( new GoogleAnalytics() );
        $this->register( new GoogleAdsense() );
        $this->register( new Mediavine() );
        $this->register( new WooCommerce() );
        $this->register( new AffiliateTracker() );
    }

    /**
     * Register an integration.
     *
     * @param BaseIntegration $integration The integration instance.
     * @return void
     */
    public function register( BaseIntegration $integration ) {
        $this->integrations[ $integration->get_source_id() ] = $integration;
    }

    /**
     * Get a specific integration by source ID.
     *
     * @param string $source_id The source identifier.
     * @return BaseIntegration|null
     */
    public function get_integration( $source_id ) {
        return isset( $this->integrations[ $source_id ] ) ? $this->integrations[ $source_id ] : null;
    }

    /**
     * Get all registered integrations.
     *
     * @return array<string, BaseIntegration>
     */
    public function get_all() {
        return $this->integrations;
    }

    /**
     * Get all connected integrations.
     *
     * @return array<string, BaseIntegration>
     */
    public function get_connected() {
        $connected = array();

        foreach ( $this->integrations as $id => $integration ) {
            if ( $integration->is_connected() ) {
                $connected[ $id ] = $integration;
            }
        }

        return $connected;
    }

    /**
     * Get all enabled integrations (user has turned them on in settings).
     *
     * @return array<string, BaseIntegration>
     */
    public function get_enabled() {
        $enabled_sources = get_option( 'ppp_enabled_sources', array() );
        $enabled         = array();

        foreach ( $this->integrations as $id => $integration ) {
            if ( ! empty( $enabled_sources[ $id ] ) ) {
                $enabled[ $id ] = $integration;
            }
        }

        return $enabled;
    }

    /**
     * Get integration info for all registered integrations.
     *
     * @return array
     */
    public function get_all_info() {
        $info = array();

        foreach ( $this->integrations as $id => $integration ) {
            $info[ $id ] = $integration->get_info();
        }

        return $info;
    }

    /**
     * Get connection status for all integrations.
     *
     * @return array
     */
    public function get_all_statuses() {
        $statuses = array();

        foreach ( $this->integrations as $id => $integration ) {
            $statuses[ $id ] = array(
                'source_id'   => $id,
                'source_name' => $integration->get_source_name(),
                'status'      => $integration->get_status(),
                'last_synced' => $integration->get_last_synced(),
            );
        }

        return $statuses;
    }

    /**
     * Test connection for a specific integration.
     *
     * @param string $source_id The source identifier.
     * @return array Result array.
     */
    public function test_connection( $source_id ) {
        $integration = $this->get_integration( $source_id );

        if ( ! $integration ) {
            return array(
                'success' => false,
                'message' => __( 'Integration not found.', 'profit-per-post' ),
            );
        }

        return $integration->test_connection();
    }

    /**
     * Test all connections.
     *
     * @return array Results keyed by source ID.
     */
    public function test_all_connections() {
        $results = array();

        foreach ( $this->integrations as $id => $integration ) {
            if ( $integration->is_connected() ) {
                $results[ $id ] = $integration->test_connection();
            } else {
                $results[ $id ] = array(
                    'success' => false,
                    'message' => __( 'Not connected.', 'profit-per-post' ),
                );
            }
        }

        return $results;
    }

    /**
     * Connect an integration.
     *
     * @param string $source_id   The source identifier.
     * @param array  $credentials The credentials.
     * @return array Result array.
     */
    public function connect( $source_id, $credentials ) {
        $integration = $this->get_integration( $source_id );

        if ( ! $integration ) {
            return array(
                'success' => false,
                'message' => __( 'Integration not found.', 'profit-per-post' ),
            );
        }

        $result = $integration->connect( $credentials );

        if ( $result['success'] ) {
            // Enable the source.
            $enabled_sources = get_option( 'ppp_enabled_sources', array() );
            $enabled_sources[ $source_id ] = true;
            update_option( 'ppp_enabled_sources', $enabled_sources );
        }

        return $result;
    }

    /**
     * Disconnect an integration.
     *
     * @param string $source_id The source identifier.
     * @return array Result array.
     */
    public function disconnect( $source_id ) {
        $integration = $this->get_integration( $source_id );

        if ( ! $integration ) {
            return array(
                'success' => false,
                'message' => __( 'Integration not found.', 'profit-per-post' ),
            );
        }

        $success = $integration->disconnect();

        if ( $success ) {
            // Disable the source.
            $enabled_sources = get_option( 'ppp_enabled_sources', array() );
            $enabled_sources[ $source_id ] = false;
            update_option( 'ppp_enabled_sources', $enabled_sources );
        }

        return array(
            'success' => $success,
            'message' => $success
                ? __( 'Integration disconnected successfully.', 'profit-per-post' )
                : __( 'Failed to disconnect integration.', 'profit-per-post' ),
        );
    }

    /**
     * Get OAuth authorization URL for an integration.
     *
     * @param string $source_id The source identifier.
     * @return string|false
     */
    public function get_auth_url( $source_id ) {
        $integration = $this->get_integration( $source_id );

        if ( ! $integration || ! $integration->requires_oauth() ) {
            return false;
        }

        return $integration->get_auth_url();
    }

    /**
     * Handle OAuth callback for an integration.
     *
     * @param string $source_id The source identifier.
     * @param string $code      The authorization code.
     * @return array Result array.
     */
    public function handle_oauth_callback( $source_id, $code ) {
        $integration = $this->get_integration( $source_id );

        if ( ! $integration ) {
            return array(
                'success' => false,
                'message' => __( 'Integration not found.', 'profit-per-post' ),
            );
        }

        $result = $integration->handle_oauth_callback( $code );

        if ( $result['success'] ) {
            $enabled_sources = get_option( 'ppp_enabled_sources', array() );
            $enabled_sources[ $source_id ] = true;
            update_option( 'ppp_enabled_sources', $enabled_sources );
        }

        return $result;
    }
}
