<?php
/**
 * Currency Formatter - formats monetary values.
 *
 * @package ProfitPerPost\Utilities
 */

namespace ProfitPerPost\Utilities;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CurrencyFormatter
 *
 * Handles currency formatting and display.
 */
class CurrencyFormatter {

    /**
     * Currency symbols map.
     *
     * @var array
     */
    private static $symbols = array(
        'USD' => '$',
        'EUR' => "\u{20AC}",
        'GBP' => "\u{00A3}",
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'JPY' => "\u{00A5}",
        'INR' => "\u{20B9}",
        'BRL' => 'R$',
        'MXN' => 'MX$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'NZD' => 'NZ$',
        'SGD' => 'S$',
        'HKD' => 'HK$',
        'KRW' => "\u{20A9}",
        'PLN' => "z\u{0142}",
        'CZK' => "K\u{010D}",
        'HUF' => 'Ft',
        'ZAR' => 'R',
    );

    /**
     * Currencies with no decimal places.
     *
     * @var array
     */
    private static $no_decimals = array( 'JPY', 'KRW', 'HUF' );

    /**
     * Format a monetary amount.
     *
     * @param float  $amount   The amount to format.
     * @param string $currency Currency code (default from settings).
     * @param bool   $compact  Use compact notation for large numbers.
     * @return string Formatted amount.
     */
    public static function format( $amount, $currency = null, $compact = false ) {
        if ( null === $currency ) {
            $currency = get_option( 'ppp_currency', 'USD' );
        }

        $symbol   = self::get_symbol( $currency );
        $decimals = in_array( $currency, self::$no_decimals, true ) ? 0 : 2;

        if ( $compact && abs( $amount ) >= 1000 ) {
            return $symbol . self::compact_number( $amount );
        }

        $formatted = number_format( $amount, $decimals, '.', ',' );

        return $symbol . $formatted;
    }

    /**
     * Format amount without currency symbol (for data/API use).
     *
     * @param float  $amount   The amount.
     * @param string $currency Currency code.
     * @return string
     */
    public static function format_plain( $amount, $currency = null ) {
        if ( null === $currency ) {
            $currency = get_option( 'ppp_currency', 'USD' );
        }

        $decimals = in_array( $currency, self::$no_decimals, true ) ? 0 : 2;
        return number_format( $amount, $decimals, '.', '' );
    }

    /**
     * Get currency symbol.
     *
     * @param string $currency Currency code.
     * @return string
     */
    public static function get_symbol( $currency = null ) {
        if ( null === $currency ) {
            $currency = get_option( 'ppp_currency', 'USD' );
        }

        return isset( self::$symbols[ $currency ] ) ? self::$symbols[ $currency ] : $currency . ' ';
    }

    /**
     * Compact number formatting (e.g., 1.2K, 3.4M).
     *
     * @param float $number The number to compact.
     * @return string
     */
    private static function compact_number( $number ) {
        $abs = abs( $number );
        $sign = $number < 0 ? '-' : '';

        if ( $abs >= 1000000 ) {
            return $sign . round( $abs / 1000000, 1 ) . 'M';
        } elseif ( $abs >= 1000 ) {
            return $sign . round( $abs / 1000, 1 ) . 'K';
        }

        return $sign . number_format( $abs, 2 );
    }

    /**
     * Get all available currencies.
     *
     * @return array Currency code => display name pairs.
     */
    public static function get_available_currencies() {
        return array(
            'USD' => __( 'US Dollar ($)', 'profit-per-post' ),
            'EUR' => __( 'Euro (EUR)', 'profit-per-post' ),
            'GBP' => __( 'British Pound (GBP)', 'profit-per-post' ),
            'CAD' => __( 'Canadian Dollar (CA$)', 'profit-per-post' ),
            'AUD' => __( 'Australian Dollar (A$)', 'profit-per-post' ),
            'JPY' => __( 'Japanese Yen (JPY)', 'profit-per-post' ),
            'INR' => __( 'Indian Rupee (INR)', 'profit-per-post' ),
            'BRL' => __( 'Brazilian Real (R$)', 'profit-per-post' ),
            'MXN' => __( 'Mexican Peso (MX$)', 'profit-per-post' ),
            'CHF' => __( 'Swiss Franc (CHF)', 'profit-per-post' ),
            'SEK' => __( 'Swedish Krona (kr)', 'profit-per-post' ),
            'NOK' => __( 'Norwegian Krone (kr)', 'profit-per-post' ),
            'DKK' => __( 'Danish Krone (kr)', 'profit-per-post' ),
            'NZD' => __( 'New Zealand Dollar (NZ$)', 'profit-per-post' ),
            'SGD' => __( 'Singapore Dollar (S$)', 'profit-per-post' ),
            'HKD' => __( 'Hong Kong Dollar (HK$)', 'profit-per-post' ),
            'KRW' => __( 'South Korean Won (KRW)', 'profit-per-post' ),
            'PLN' => __( 'Polish Zloty (PLN)', 'profit-per-post' ),
            'CZK' => __( 'Czech Koruna (CZK)', 'profit-per-post' ),
            'HUF' => __( 'Hungarian Forint (HUF)', 'profit-per-post' ),
            'ZAR' => __( 'South African Rand (R)', 'profit-per-post' ),
        );
    }

    /**
     * Format a percentage value.
     *
     * @param float $value     The percentage value.
     * @param bool  $show_sign Whether to show + sign for positive values.
     * @return string
     */
    public static function format_percentage( $value, $show_sign = true ) {
        $formatted = number_format( abs( $value ), 1 );

        if ( $show_sign ) {
            if ( $value > 0 ) {
                return '+' . $formatted . '%';
            } elseif ( $value < 0 ) {
                return '-' . $formatted . '%';
            }
        }

        return $formatted . '%';
    }

    /**
     * Format a number with compact notation.
     *
     * @param int|float $number The number to format.
     * @return string
     */
    public static function format_number( $number ) {
        if ( $number >= 1000000 ) {
            return round( $number / 1000000, 1 ) . 'M';
        } elseif ( $number >= 1000 ) {
            return round( $number / 1000, 1 ) . 'K';
        }

        return number_format( $number );
    }
}
