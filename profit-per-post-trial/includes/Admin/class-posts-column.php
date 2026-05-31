<?php
/**
 * Posts Column - adds revenue column to WP Posts list.
 *
 * @package ProfitPerPost\Admin
 */

namespace ProfitPerPost\Admin;

use ProfitPerPost\Revenue\RevenueCalculator;
use ProfitPerPost\Utilities\CurrencyFormatter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PostsColumn
 *
 * Adds a "Revenue" column to the WordPress Posts list table
 * showing the last 30 days of revenue for each post.
 */
class PostsColumn {

    /**
     * Revenue calculator instance.
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
     * Initialize column hooks.
     *
     * @return void
     */
    public function init() {
        if ( ! get_option( 'ppp_show_revenue_column', true ) ) {
            return;
        }

        add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
        add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-post_sortable_columns', array( $this, 'make_sortable' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_sort' ) );
    }

    /**
     * Add the revenue column header.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_column( $columns ) {
        // Insert before the date column.
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $new_columns['ppp_revenue'] = __( 'Revenue (30d)', 'profit-per-post' );
            }
            $new_columns[ $key ] = $label;
        }

        // If date column wasn't found, add at end.
        if ( ! isset( $new_columns['ppp_revenue'] ) ) {
            $new_columns['ppp_revenue'] = __( 'Revenue (30d)', 'profit-per-post' );
        }

        return $new_columns;
    }

    /**
     * Render the revenue column content.
     *
     * @param string $column_name The column identifier.
     * @param int    $post_id     The post ID.
     * @return void
     */
    public function render_column( $column_name, $post_id ) {
        if ( 'ppp_revenue' !== $column_name ) {
            return;
        }

        $revenue = $this->calculator->get_post_revenue_last_30_days( $post_id );

        if ( $revenue > 0 ) {
            $formatted = CurrencyFormatter::format( $revenue, null, true );
            printf(
                '<span style="color: #16a34a; font-weight: 600;">%s</span>',
                esc_html( $formatted )
            );
        } else {
            printf(
                '<span style="color: #9ca3af;">%s</span>',
                esc_html( CurrencyFormatter::format( 0 ) )
            );
        }
    }

    /**
     * Make the revenue column sortable.
     *
     * @param array $columns Sortable columns.
     * @return array
     */
    public function make_sortable( $columns ) {
        $columns['ppp_revenue'] = 'ppp_revenue';
        return $columns;
    }

    /**
     * Handle sorting by revenue column.
     *
     * @param \WP_Query $query The query object.
     * @return void
     */
    public function handle_sort( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'ppp_revenue' !== $query->get( 'orderby' ) ) {
            return;
        }

        // We can't easily sort by data in a custom table via WP_Query.
        // Instead, use a subquery in the posts_clauses filter.
        add_filter( 'posts_clauses', array( $this, 'sort_by_revenue_clauses' ) );
    }

    /**
     * Modify query clauses to sort by revenue.
     *
     * @param array $clauses Query clauses.
     * @return array Modified clauses.
     */
    public function sort_by_revenue_clauses( $clauses ) {
        global $wpdb;
        $revenue_table = \ProfitPerPost\Database\Schema::revenue_table();

        $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date   = gmdate( 'Y-m-d' );

        $clauses['fields'] .= $wpdb->prepare(
            ", COALESCE((SELECT SUM(revenue_amount) FROM {$revenue_table} WHERE post_id = {$wpdb->posts}.ID AND date_recorded BETWEEN %s AND %s), 0) AS ppp_total_revenue",
            $start_date,
            $end_date
        );

        $clauses['orderby'] = 'ppp_total_revenue ' . ( 'ASC' === strtoupper( get_query_var( 'order' ) ) ? 'ASC' : 'DESC' );

        // Remove this filter after use to avoid affecting other queries.
        remove_filter( 'posts_clauses', array( $this, 'sort_by_revenue_clauses' ) );

        return $clauses;
    }
}
