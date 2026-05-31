<?php
/**
 * Base REST Controller - abstract base for all REST endpoints.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Security\Sanitizer;
use ProfitPerPost\Utilities\DateHelper;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RestController
 *
 * Abstract base class providing common REST API functionality.
 */
abstract class RestController {

    /**
     * REST namespace.
     *
     * @var string
     */
    protected $namespace = PPP_REST_NAMESPACE;

    /**
     * Register routes (implemented by subclasses).
     *
     * @return void
     */
    abstract public function register_routes();

    /**
     * Get sanitized date range from request params.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return array Array with 'start' and 'end' keys.
     */
    protected function get_date_range( $request ) {
        $period     = $request->get_param( 'period' );
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        // If custom dates provided, use those.
        if ( $start_date && $end_date ) {
            $sanitized_start = Sanitizer::sanitize_date( $start_date );
            $sanitized_end   = Sanitizer::sanitize_date( $end_date );

            if ( $sanitized_start && $sanitized_end ) {
                return array(
                    'start' => $sanitized_start,
                    'end'   => $sanitized_end,
                );
            }
        }

        // Use period preset.
        if ( ! $period ) {
            $period = get_option( 'ppp_default_date_range', '30' ) . 'd';
        }

        return DateHelper::get_date_range( $period );
    }

    /**
     * Get pagination params from request.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return array
     */
    protected function get_pagination( $request ) {
        $page     = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: get_option( 'ppp_posts_per_page', 20 );

        return Sanitizer::sanitize_pagination( $page, $per_page );
    }

    /**
     * Create a success response.
     *
     * @param mixed $data    Response data.
     * @param int   $status  HTTP status code.
     * @return \WP_REST_Response
     */
    protected function success( $data, $status = 200 ) {
        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => $data,
        ), $status );
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @param string $code    Error code.
     * @return \WP_REST_Response
     */
    protected function error( $message, $status = 400, $code = 'ppp_error' ) {
        return new \WP_REST_Response( array(
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ), $status );
    }

    /**
     * Common date range arguments for route registration.
     *
     * @return array
     */
    protected function get_date_args() {
        return array(
            'period' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'start_date' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'end_date' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Common pagination arguments.
     *
     * @return array
     */
    protected function get_pagination_args() {
        return array(
            'page' => array(
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'type'              => 'integer',
                'default'           => 20,
                'sanitize_callback' => 'absint',
            ),
        );
    }
}
