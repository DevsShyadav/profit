<?php
/**
 * Export Endpoint - data export functionality.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Revenue\RevenueCalculator;
use ProfitPerPost\Utilities\ExportGenerator;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ExportEndpoint
 *
 * REST endpoints for exporting revenue data as CSV or JSON.
 */
class ExportEndpoint extends RestController {

    /**
     * Revenue calculator.
     *
     * @var RevenueCalculator
     */
    private $calculator;

    /**
     * Constructor.
     *
     * @param RevenueCalculator $calculator Revenue calculator.
     */
    public function __construct( RevenueCalculator $calculator ) {
        $this->calculator = $calculator;
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/export/csv', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_csv' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_export' ),
            'args'                => $this->get_date_args(),
        ));

        register_rest_route( $this->namespace, '/export/json', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_json' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_export' ),
            'args'                => $this->get_date_args(),
        ));
    }

    /**
     * Export data as CSV download.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response|void
     */
    public function export_csv( $request ) {
        $range = $this->get_date_range( $request );

        $csv_content = ExportGenerator::generate_csv( $range['start'], $range['end'] );
        $filename    = ExportGenerator::get_filename( $range['start'], $range['end'], 'csv' );

        // Send download.
        ExportGenerator::send_csv_download( $csv_content, $filename );
    }

    /**
     * Export data as JSON.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function export_json( $request ) {
        $range  = $this->get_date_range( $request );
        $report = ExportGenerator::generate_json_report( $range['start'], $range['end'] );

        return $this->success( $report );
    }
}
