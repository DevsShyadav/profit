<?php
/**
 * Dashboard Endpoint - provides dashboard overview data.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Revenue\RevenueCalculator;
use ProfitPerPost\Revenue\RevenueAggregator;
use ProfitPerPost\Cache\CacheManager;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DashboardEndpoint
 *
 * REST endpoint for dashboard overview data including
 * totals, top posts, trends, and insights.
 */
class DashboardEndpoint extends RestController {

    /**
     * Revenue calculator.
     *
     * @var RevenueCalculator
     */
    private $calculator;

    /**
     * Cache manager.
     *
     * @var CacheManager
     */
    private $cache;

    /**
     * Constructor.
     *
     * @param RevenueCalculator $calculator Revenue calculator.
     * @param CacheManager      $cache      Cache manager.
     */
    public function __construct( RevenueCalculator $calculator, CacheManager $cache ) {
        $this->calculator = $calculator;
        $this->cache      = $cache;
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_dashboard' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            'args'                => $this->get_date_args(),
        ));

        register_rest_route( $this->namespace, '/dashboard/insights', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_insights' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            'args'                => $this->get_date_args(),
        ));
    }

    /**
     * Get dashboard data.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_dashboard( $request ) {
        $range = $this->get_date_range( $request );
        $data  = $this->calculator->get_dashboard_data( $range['start'], $range['end'] );

        return $this->success( $data );
    }

    /**
     * Get insights/recommendations.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_insights( $request ) {
        $range      = $this->get_date_range( $request );
        $aggregator = new RevenueAggregator();
        $insights   = $aggregator->generate_insights( $range['start'], $range['end'] );

        return $this->success( $insights );
    }
}
