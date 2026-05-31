<?php
/**
 * Main Plugin class - the orchestrator.
 *
 * @package ProfitPerPost
 */

namespace ProfitPerPost;

use ProfitPerPost\Admin\AdminMenu;
use ProfitPerPost\Admin\AdminAssets;
use ProfitPerPost\Admin\AdminNotices;
use ProfitPerPost\Admin\PostsColumn;
use ProfitPerPost\Admin\Onboarding;
use ProfitPerPost\API\DashboardEndpoint;
use ProfitPerPost\API\PostsEndpoint;
use ProfitPerPost\API\SettingsEndpoint;
use ProfitPerPost\API\ConnectionsEndpoint;
use ProfitPerPost\API\SyncEndpoint;
use ProfitPerPost\API\ExportEndpoint;
use ProfitPerPost\API\AIEndpoint;
use ProfitPerPost\Integrations\IntegrationManager;
use ProfitPerPost\Sync\SyncScheduler;
use ProfitPerPost\Sync\SyncManager;
use ProfitPerPost\Cache\CacheManager;
use ProfitPerPost\Revenue\RevenueCalculator;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 *
 * Main plugin singleton class that bootstraps all components.
 */
class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Integration manager instance.
     *
     * @var IntegrationManager
     */
    private $integration_manager;

    /**
     * Sync manager instance.
     *
     * @var SyncManager
     */
    private $sync_manager;

    /**
     * Cache manager instance.
     *
     * @var CacheManager
     */
    private $cache_manager;

    /**
     * Revenue calculator instance.
     *
     * @var RevenueCalculator
     */
    private $revenue_calculator;

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // Initialize core services.
        $this->cache_manager       = new CacheManager();
        $this->integration_manager = new IntegrationManager();
        $this->sync_manager        = new SyncManager( $this->integration_manager, $this->cache_manager );
        $this->revenue_calculator  = new RevenueCalculator( $this->cache_manager );
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton.' );
    }

    /**
     * Run the plugin - register all hooks.
     *
     * @return void
     */
    public function run() {
        // Load text domain.
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Ensure capabilities are registered (safety net for upgrades).
        add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );

        // Initialize components.
        $this->init_admin();
        $this->init_rest_api();
        $this->init_sync();
        $this->init_frontend_tracking();

        // Custom hooks for extensibility.
        do_action( 'ppp_plugin_loaded', $this );
    }

    /**
     * Ensure custom capabilities exist (runs on admin_init as safety net).
     *
     * @return void
     */
    public function ensure_capabilities() {
        // Only run once per version update.
        $caps_version = get_option( 'ppp_caps_version', '0' );
        if ( version_compare( $caps_version, PPP_VERSION, '<' ) ) {
            CapabilityManager::register_capabilities();
            update_option( 'ppp_caps_version', PPP_VERSION );
        }
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'profit-per-post',
            false,
            dirname( PPP_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Initialize admin components.
     *
     * @return void
     */
    private function init_admin() {
        if ( ! is_admin() ) {
            return;
        }

        // Admin menu.
        $admin_menu = new AdminMenu();
        $admin_menu->init();

        // Admin assets.
        $admin_assets = new AdminAssets();
        $admin_assets->init();

        // Admin notices.
        $admin_notices = new AdminNotices();
        $admin_notices->init();

        // Posts list column.
        $posts_column = new PostsColumn( $this->revenue_calculator );
        $posts_column->init();

        // Onboarding wizard.
        $onboarding = new Onboarding();
        $onboarding->init();
    }

    /**
     * Initialize REST API endpoints.
     *
     * @return void
     */
    private function init_rest_api() {
        add_action( 'rest_api_init', function() {
            $dashboard = new DashboardEndpoint( $this->revenue_calculator, $this->cache_manager );
            $dashboard->register_routes();

            $posts = new PostsEndpoint( $this->revenue_calculator );
            $posts->register_routes();

            $settings = new SettingsEndpoint();
            $settings->register_routes();

            $connections = new ConnectionsEndpoint( $this->integration_manager );
            $connections->register_routes();

            $sync = new SyncEndpoint( $this->sync_manager );
            $sync->register_routes();

            $export = new ExportEndpoint( $this->revenue_calculator );
            $export->register_routes();

            $ai = new AIEndpoint();
            $ai->register_routes();
        });
    }

    /**
     * Initialize sync scheduler.
     *
     * @return void
     */
    private function init_sync() {
        $scheduler = new SyncScheduler( $this->sync_manager );
        $scheduler->init();
    }

    /**
     * Initialize frontend tracking (affiliate clicks, WooCommerce attribution).
     *
     * @return void
     */
    private function init_frontend_tracking() {
        if ( is_admin() ) {
            return;
        }

        // Affiliate click tracking.
        $affiliate = $this->integration_manager->get_integration( 'affiliate' );
        if ( $affiliate ) {
            $affiliate->init_frontend_tracking();
        }

        // WooCommerce referral tracking.
        $woocommerce = $this->integration_manager->get_integration( 'woocommerce' );
        if ( $woocommerce ) {
            $woocommerce->init_frontend_tracking();
        }
    }

    /**
     * Get integration manager.
     *
     * @return IntegrationManager
     */
    public function get_integration_manager() {
        return $this->integration_manager;
    }

    /**
     * Get sync manager.
     *
     * @return SyncManager
     */
    public function get_sync_manager() {
        return $this->sync_manager;
    }

    /**
     * Get cache manager.
     *
     * @return CacheManager
     */
    public function get_cache_manager() {
        return $this->cache_manager;
    }

    /**
     * Get revenue calculator.
     *
     * @return RevenueCalculator
     */
    public function get_revenue_calculator() {
        return $this->revenue_calculator;
    }
}
