<?php
/**
 * Attribution Model - determines revenue attribution rules.
 *
 * @package ProfitPerPost\Revenue
 */

namespace ProfitPerPost\Revenue;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AttributionModel
 *
 * Handles different attribution models for determining
 * which post gets credit for a conversion/sale.
 */
class AttributionModel {

    /**
     * First touch attribution - credits the first post visited.
     *
     * @var string
     */
    const FIRST_TOUCH = 'first_touch';

    /**
     * Last touch attribution - credits the last post visited before conversion.
     *
     * @var string
     */
    const LAST_TOUCH = 'last_touch';

    /**
     * Linear attribution - splits credit equally among all posts in the journey.
     *
     * @var string
     */
    const LINEAR = 'linear';

    /**
     * Get the configured attribution model.
     *
     * @return string
     */
    public static function get_current_model() {
        return get_option( 'ppp_attribution_model', self::LAST_TOUCH );
    }

    /**
     * Get all available attribution models.
     *
     * @return array
     */
    public static function get_available_models() {
        return array(
            self::FIRST_TOUCH => array(
                'id'          => self::FIRST_TOUCH,
                'name'        => __( 'First Touch', 'profit-per-post' ),
                'description' => __( 'Credits the first blog post the visitor read before purchasing.', 'profit-per-post' ),
            ),
            self::LAST_TOUCH => array(
                'id'          => self::LAST_TOUCH,
                'name'        => __( 'Last Touch', 'profit-per-post' ),
                'description' => __( 'Credits the last blog post the visitor read before purchasing.', 'profit-per-post' ),
            ),
            self::LINEAR => array(
                'id'          => self::LINEAR,
                'name'        => __( 'Linear', 'profit-per-post' ),
                'description' => __( 'Splits credit equally among all posts the visitor read.', 'profit-per-post' ),
            ),
        );
    }

    /**
     * Attribute a conversion to a post based on the current model.
     *
     * @param float $revenue   The revenue amount.
     * @param array $post_ids  Array of post IDs in the user's journey (ordered by visit time).
     * @return array Array of post_id => attributed_revenue pairs.
     */
    public static function attribute( $revenue, $post_ids ) {
        if ( empty( $post_ids ) || $revenue <= 0 ) {
            return array();
        }

        $model = self::get_current_model();

        switch ( $model ) {
            case self::FIRST_TOUCH:
                return self::attribute_first_touch( $revenue, $post_ids );

            case self::LINEAR:
                return self::attribute_linear( $revenue, $post_ids );

            case self::LAST_TOUCH:
            default:
                return self::attribute_last_touch( $revenue, $post_ids );
        }
    }

    /**
     * First touch attribution - all credit to first post.
     *
     * @param float $revenue  The revenue amount.
     * @param array $post_ids Ordered post IDs.
     * @return array
     */
    private static function attribute_first_touch( $revenue, $post_ids ) {
        $first_post = reset( $post_ids );
        return array( $first_post => $revenue );
    }

    /**
     * Last touch attribution - all credit to last post.
     *
     * @param float $revenue  The revenue amount.
     * @param array $post_ids Ordered post IDs.
     * @return array
     */
    private static function attribute_last_touch( $revenue, $post_ids ) {
        $last_post = end( $post_ids );
        return array( $last_post => $revenue );
    }

    /**
     * Linear attribution - equal credit to all posts.
     *
     * @param float $revenue  The revenue amount.
     * @param array $post_ids Ordered post IDs.
     * @return array
     */
    private static function attribute_linear( $revenue, $post_ids ) {
        $unique_posts = array_unique( $post_ids );
        $count        = count( $unique_posts );

        if ( 0 === $count ) {
            return array();
        }

        $per_post = $revenue / $count;
        $result   = array();

        foreach ( $unique_posts as $post_id ) {
            $result[ $post_id ] = round( $per_post, 4 );
        }

        return $result;
    }

    /**
     * Update the attribution model setting.
     *
     * @param string $model The model identifier.
     * @return bool
     */
    public static function set_model( $model ) {
        $valid_models = array( self::FIRST_TOUCH, self::LAST_TOUCH, self::LINEAR );

        if ( ! in_array( $model, $valid_models, true ) ) {
            return false;
        }

        return update_option( 'ppp_attribution_model', $model );
    }
}
