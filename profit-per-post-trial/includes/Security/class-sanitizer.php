<?php
/**
 * Input Sanitization utility.
 *
 * @package ProfitPerPost\Security
 */

namespace ProfitPerPost\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Sanitizer
 *
 * Provides comprehensive input sanitization methods
 * beyond WordPress's built-in sanitize_* functions.
 */
class Sanitizer {

    /**
     * Sanitize a date string.
     *
     * @param string $date   The date string.
     * @param string $format Expected format (default Y-m-d).
     * @return string|false Sanitized date string or false if invalid.
     */
    public static function sanitize_date( $date, $format = 'Y-m-d' ) {
        $date = sanitize_text_field( $date );

        $datetime = \DateTime::createFromFormat( $format, $date );
        if ( $datetime && $datetime->format( $format ) === $date ) {
            return $date;
        }

        return false;
    }

    /**
     * Sanitize a date range (start and end date).
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @return array|false Array with 'start' and 'end' keys, or false if invalid.
     */
    public static function sanitize_date_range( $start_date, $end_date ) {
        $start = self::sanitize_date( $start_date );
        $end   = self::sanitize_date( $end_date );

        if ( ! $start || ! $end ) {
            return false;
        }

        // Ensure start is before end.
        if ( strtotime( $start ) > strtotime( $end ) ) {
            return false;
        }

        return array(
            'start' => $start,
            'end'   => $end,
        );
    }

    /**
     * Sanitize a revenue amount.
     *
     * @param mixed $amount The amount value.
     * @return float Sanitized amount (always >= 0).
     */
    public static function sanitize_amount( $amount ) {
        $amount = floatval( $amount );
        return max( 0.0, $amount );
    }

    /**
     * Sanitize a source identifier.
     *
     * @param string $source The source string.
     * @return string Sanitized source (lowercase alphanumeric + underscore only).
     */
    public static function sanitize_source( $source ) {
        $source = sanitize_text_field( $source );
        $source = strtolower( $source );
        $source = preg_replace( '/[^a-z0-9_]/', '', $source );
        return $source;
    }

    /**
     * Sanitize a URL for affiliate tracking.
     *
     * @param string $url The URL to sanitize.
     * @return string|false Sanitized URL or false if invalid.
     */
    public static function sanitize_url( $url ) {
        $url = esc_url_raw( $url );

        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        return $url;
    }

    /**
     * Sanitize a currency code.
     *
     * @param string $currency The currency code.
     * @return string Sanitized 3-letter currency code.
     */
    public static function sanitize_currency( $currency ) {
        $currency = strtoupper( sanitize_text_field( $currency ) );
        $currency = preg_replace( '/[^A-Z]/', '', $currency );

        if ( strlen( $currency ) !== 3 ) {
            return 'USD';
        }

        $valid_currencies = array(
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'INR',
            'BRL', 'MXN', 'CHF', 'SEK', 'NOK', 'DKK', 'NZD',
            'SGD', 'HKD', 'KRW', 'PLN', 'CZK', 'HUF', 'ZAR',
        );

        return in_array( $currency, $valid_currencies, true ) ? $currency : 'USD';
    }

    /**
     * Sanitize pagination parameters.
     *
     * @param mixed $page     The page number.
     * @param mixed $per_page The per page count.
     * @return array Sanitized pagination array.
     */
    public static function sanitize_pagination( $page, $per_page ) {
        $page     = max( 1, absint( $page ) );
        $per_page = min( 100, max( 1, absint( $per_page ) ) );

        return array(
            'page'     => $page,
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
    }

    /**
     * Sanitize sort parameters.
     *
     * @param string $order_by     The field to order by.
     * @param string $order        The order direction.
     * @param array  $allowed_fields Allowed field names.
     * @return array Sanitized sort parameters.
     */
    public static function sanitize_sort( $order_by, $order, $allowed_fields = array() ) {
        $order = strtoupper( sanitize_text_field( $order ) );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
            $order = 'DESC';
        }

        $order_by = sanitize_text_field( $order_by );
        if ( ! empty( $allowed_fields ) && ! in_array( $order_by, $allowed_fields, true ) ) {
            $order_by = $allowed_fields[0];
        }

        return array(
            'order_by' => $order_by,
            'order'    => $order,
        );
    }

    /**
     * Sanitize an API key.
     *
     * @param string $key The API key.
     * @return string Sanitized key (alphanumeric + hyphens + underscores + dots).
     */
    public static function sanitize_api_key( $key ) {
        $key = sanitize_text_field( $key );
        $key = preg_replace( '/[^a-zA-Z0-9\-_\.]/', '', $key );
        return $key;
    }

    /**
     * Sanitize a settings array.
     *
     * @param array $settings   The settings to sanitize.
     * @param array $schema     Schema defining types for each setting key.
     * @return array Sanitized settings.
     */
    public static function sanitize_settings( $settings, $schema ) {
        $sanitized = array();

        foreach ( $schema as $key => $type ) {
            if ( ! isset( $settings[ $key ] ) ) {
                continue;
            }

            switch ( $type ) {
                case 'string':
                    $sanitized[ $key ] = sanitize_text_field( $settings[ $key ] );
                    break;

                case 'int':
                case 'integer':
                    $sanitized[ $key ] = absint( $settings[ $key ] );
                    break;

                case 'float':
                    $sanitized[ $key ] = floatval( $settings[ $key ] );
                    break;

                case 'bool':
                case 'boolean':
                    $sanitized[ $key ] = (bool) $settings[ $key ];
                    break;

                case 'url':
                    $sanitized[ $key ] = esc_url_raw( $settings[ $key ] );
                    break;

                case 'email':
                    $sanitized[ $key ] = sanitize_email( $settings[ $key ] );
                    break;

                case 'array':
                    $sanitized[ $key ] = is_array( $settings[ $key ] )
                        ? array_map( 'sanitize_text_field', $settings[ $key ] )
                        : array();
                    break;

                case 'html':
                    $sanitized[ $key ] = wp_kses_post( $settings[ $key ] );
                    break;

                default:
                    $sanitized[ $key ] = sanitize_text_field( $settings[ $key ] );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize JSON input string.
     *
     * @param string $json The JSON string.
     * @return array|false Decoded and sanitized array or false.
     */
    public static function sanitize_json( $json ) {
        if ( is_array( $json ) ) {
            return $json;
        }

        $decoded = json_decode( sanitize_text_field( wp_unslash( $json ) ), true );

        if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        return $decoded;
    }
}
