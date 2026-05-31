<?php
/**
 * Posts Endpoint - per-post revenue data.
 *
 * @package ProfitPerPost\API
 */

namespace ProfitPerPost\API;

use ProfitPerPost\Revenue\RevenueCalculator;
use ProfitPerPost\Security\CapabilityManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PostsEndpoint
 *
 * REST endpoints for per-post revenue listing and detail views.
 */
class PostsEndpoint extends RestController {

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
        register_rest_route( $this->namespace, '/posts', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_posts' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            'args'                => array_merge(
                $this->get_date_args(),
                $this->get_pagination_args(),
                array(
                    'order_by' => array(
                        'type'    => 'string',
                        'default' => 'revenue',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'order' => array(
                        'type'    => 'string',
                        'default' => 'DESC',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'search' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'category' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'source' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                )
            ),
        ));

        register_rest_route( $this->namespace, '/posts/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_post_detail' ),
            'permission_callback' => array( CapabilityManager::class, 'rest_permission_view' ),
            'args'                => array_merge(
                $this->get_date_args(),
                array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                )
            ),
        ));
    }

    /**
     * Get paginated posts list with revenue data.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_posts( $request ) {
        $range      = $this->get_date_range( $request );
        $pagination = $this->get_pagination( $request );

        $order_by = $request->get_param( 'order_by' ) ?: 'revenue';
        $order    = $request->get_param( 'order' ) ?: 'DESC';

        $filters = array(
            'search'   => $request->get_param( 'search' ),
            'category' => $request->get_param( 'category' ),
            'source'   => $request->get_param( 'source' ),
        );

        // Remove empty filters.
        $filters = array_filter( $filters );

        $data = $this->calculator->get_posts_list(
            $range['start'],
            $range['end'],
            $pagination['page'],
            $pagination['per_page'],
            $order_by,
            $order,
            $filters
        );

        return $this->success( $data );
    }

    /**
     * Get detailed revenue data for a single post.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public function get_post_detail( $request ) {
        $post_id = $request->get_param( 'id' );
        $range   = $this->get_date_range( $request );

        $post = get_post( $post_id );
        if ( ! $post ) {
            return $this->error( __( 'Post not found.', 'profit-per-post' ), 404 );
        }

        $data = $this->calculator->get_post_detail( $post_id, $range['start'], $range['end'] );

        return $this->success( $data );
    }
}
