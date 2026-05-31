<?php
/**
 * Date Helper - date/time utility functions.
 *
 * @package ProfitPerPost\Utilities
 */

namespace ProfitPerPost\Utilities;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DateHelper
 *
 * Provides date/time utility methods for consistent
 * date handling across the plugin.
 */
class DateHelper {

    /**
     * Get date range from a period identifier.
     *
     * @param string $period Period identifier (7d, 30d, 90d, 12m, custom).
     * @return array Array with 'start' and 'end' date strings (Y-m-d).
     */
    public static function get_date_range( $period = '30d' ) {
        $end_date = gmdate( 'Y-m-d' );

        switch ( $period ) {
            case '7d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
                break;

            case '14d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-14 days' ) );
                break;

            case '30d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
                break;

            case '90d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
                break;

            case '6m':
                $start_date = gmdate( 'Y-m-d', strtotime( '-6 months' ) );
                break;

            case '12m':
                $start_date = gmdate( 'Y-m-d', strtotime( '-12 months' ) );
                break;

            case 'this_month':
                $start_date = gmdate( 'Y-m-01' );
                break;

            case 'last_month':
                $start_date = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
                $end_date   = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) );
                break;

            case 'this_year':
                $start_date = gmdate( 'Y-01-01' );
                break;

            default:
                $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
                break;
        }

        return array(
            'start' => $start_date,
            'end'   => $end_date,
        );
    }

    /**
     * Get the previous period for comparison.
     *
     * @param string $start_date Current period start (Y-m-d).
     * @param string $end_date   Current period end (Y-m-d).
     * @return array Array with 'start' and 'end' for previous period.
     */
    public static function get_previous_period( $start_date, $end_date ) {
        $period_days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;

        $prev_end   = gmdate( 'Y-m-d', strtotime( $start_date ) - DAY_IN_SECONDS );
        $prev_start = gmdate( 'Y-m-d', strtotime( $prev_end ) - ( $period_days * DAY_IN_SECONDS ) );

        return array(
            'start' => $prev_start,
            'end'   => $prev_end,
        );
    }

    /**
     * Format a date for display based on plugin settings.
     *
     * @param string $date The date to format (Y-m-d or datetime).
     * @return string Formatted date.
     */
    public static function format_date( $date ) {
        $format = get_option( 'ppp_date_format', 'M j, Y' );
        $timestamp = strtotime( $date );

        if ( ! $timestamp ) {
            return $date;
        }

        return gmdate( $format, $timestamp );
    }

    /**
     * Get a human-readable time ago string.
     *
     * @param string $datetime The datetime string.
     * @return string Human-readable time difference.
     */
    public static function time_ago( $datetime ) {
        if ( empty( $datetime ) ) {
            return __( 'Never', 'profit-per-post' );
        }

        $timestamp = strtotime( $datetime );
        if ( ! $timestamp ) {
            return $datetime;
        }

        return human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'profit-per-post' );
    }

    /**
     * Get the number of days between two dates.
     *
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date   End date (Y-m-d).
     * @return int Number of days.
     */
    public static function days_between( $start_date, $end_date ) {
        $diff = strtotime( $end_date ) - strtotime( $start_date );
        return max( 0, (int) floor( $diff / DAY_IN_SECONDS ) );
    }

    /**
     * Check if a date string is valid.
     *
     * @param string $date   The date string.
     * @param string $format Expected format.
     * @return bool
     */
    public static function is_valid_date( $date, $format = 'Y-m-d' ) {
        $datetime = \DateTime::createFromFormat( $format, $date );
        return $datetime && $datetime->format( $format ) === $date;
    }

    /**
     * Get available date range presets for the UI.
     *
     * @return array
     */
    public static function get_presets() {
        return array(
            '7d'         => __( 'Last 7 Days', 'profit-per-post' ),
            '14d'        => __( 'Last 14 Days', 'profit-per-post' ),
            '30d'        => __( 'Last 30 Days', 'profit-per-post' ),
            '90d'        => __( 'Last 90 Days', 'profit-per-post' ),
            '6m'         => __( 'Last 6 Months', 'profit-per-post' ),
            '12m'        => __( 'Last 12 Months', 'profit-per-post' ),
            'this_month' => __( 'This Month', 'profit-per-post' ),
            'last_month' => __( 'Last Month', 'profit-per-post' ),
            'this_year'  => __( 'This Year', 'profit-per-post' ),
        );
    }
}
