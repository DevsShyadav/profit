<?php
/**
 * Encryption utility for securing API tokens and credentials.
 *
 * @package ProfitPerPost\Security
 */

namespace ProfitPerPost\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Encryption
 *
 * Provides AES-256-CBC encryption/decryption for sensitive data
 * such as OAuth tokens and API keys.
 */
class Encryption {

    /**
     * Cipher method.
     *
     * @var string
     */
    const CIPHER = 'aes-256-cbc';

    /**
     * Get the encryption key.
     *
     * @return string
     */
    private static function get_key() {
        $key = get_option( PPP_ENCRYPTION_KEY_OPTION, '' );

        if ( empty( $key ) ) {
            $key = wp_generate_password( 64, true, true );
            update_option( PPP_ENCRYPTION_KEY_OPTION, $key );
        }

        // Derive a proper 256-bit key using hash.
        return hash( 'sha256', $key, true );
    }

    /**
     * Encrypt a value.
     *
     * @param mixed $value The value to encrypt (will be JSON encoded if not a string).
     * @return string|false Base64 encoded encrypted string, or false on failure.
     */
    public static function encrypt( $value ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return false;
        }

        $key = self::get_key();

        if ( ! is_string( $value ) ) {
            $value = wp_json_encode( $value );
        }

        $iv_length = openssl_cipher_iv_length( self::CIPHER );
        $iv        = openssl_random_pseudo_bytes( $iv_length );

        $encrypted = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $encrypted ) {
            return false;
        }

        // Create HMAC for integrity verification.
        $hmac = hash_hmac( 'sha256', $iv . $encrypted, $key, true );

        // Combine: HMAC + IV + encrypted data, then base64 encode.
        $combined = base64_encode( $hmac . $iv . $encrypted );

        return $combined;
    }

    /**
     * Decrypt a value.
     *
     * @param string $encrypted_value Base64 encoded encrypted string.
     * @return mixed|false The decrypted value, or false on failure.
     */
    public static function decrypt( $encrypted_value ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return false;
        }

        if ( empty( $encrypted_value ) ) {
            return false;
        }

        $key = self::get_key();

        $decoded = base64_decode( $encrypted_value, true );
        if ( false === $decoded ) {
            return false;
        }

        $hmac_length = 32; // SHA-256 produces 32 bytes.
        $iv_length   = openssl_cipher_iv_length( self::CIPHER );

        // Ensure decoded data is long enough.
        if ( strlen( $decoded ) < ( $hmac_length + $iv_length + 1 ) ) {
            return false;
        }

        // Extract components.
        $hmac      = substr( $decoded, 0, $hmac_length );
        $iv        = substr( $decoded, $hmac_length, $iv_length );
        $encrypted = substr( $decoded, $hmac_length + $iv_length );

        // Verify HMAC integrity.
        $expected_hmac = hash_hmac( 'sha256', $iv . $encrypted, $key, true );
        if ( ! hash_equals( $expected_hmac, $hmac ) ) {
            return false;
        }

        // Decrypt.
        $decrypted = openssl_decrypt( $encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $decrypted ) {
            return false;
        }

        // Try to JSON decode.
        $json_decoded = json_decode( $decrypted, true );
        if ( null !== $json_decoded ) {
            return $json_decoded;
        }

        return $decrypted;
    }

    /**
     * Encrypt an array of credentials.
     *
     * @param array $credentials The credentials array.
     * @return string|false Encrypted string or false on failure.
     */
    public static function encrypt_credentials( $credentials ) {
        return self::encrypt( wp_json_encode( $credentials ) );
    }

    /**
     * Decrypt credentials back to array.
     *
     * @param string $encrypted_credentials The encrypted credentials string.
     * @return array|false The credentials array or false on failure.
     */
    public static function decrypt_credentials( $encrypted_credentials ) {
        $decrypted = self::decrypt( $encrypted_credentials );

        if ( false === $decrypted ) {
            return false;
        }

        if ( is_array( $decrypted ) ) {
            return $decrypted;
        }

        if ( is_string( $decrypted ) ) {
            $decoded = json_decode( $decrypted, true );
            return is_array( $decoded ) ? $decoded : false;
        }

        return false;
    }

    /**
     * Check if encryption is available.
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
    }

    /**
     * Rotate the encryption key (re-encrypts all stored credentials).
     *
     * @return bool True on success, false on failure.
     */
    public static function rotate_key() {
        global $wpdb;

        $connections_table = \ProfitPerPost\Database\Schema::connections_table();

        // Get all existing connections.
        $connections = $wpdb->get_results(
            "SELECT id, credentials FROM {$connections_table}",
            ARRAY_A
        );

        if ( empty( $connections ) ) {
            // No credentials to re-encrypt, just generate new key.
            $new_key = wp_generate_password( 64, true, true );
            update_option( PPP_ENCRYPTION_KEY_OPTION, $new_key );
            return true;
        }

        // Decrypt all with old key.
        $decrypted_connections = array();
        foreach ( $connections as $conn ) {
            $creds = self::decrypt_credentials( $conn['credentials'] );
            if ( false !== $creds ) {
                $decrypted_connections[ $conn['id'] ] = $creds;
            }
        }

        // Generate new key.
        $new_key = wp_generate_password( 64, true, true );
        update_option( PPP_ENCRYPTION_KEY_OPTION, $new_key );

        // Re-encrypt with new key.
        foreach ( $decrypted_connections as $id => $creds ) {
            $new_encrypted = self::encrypt_credentials( $creds );
            if ( false !== $new_encrypted ) {
                $wpdb->update(
                    $connections_table,
                    array( 'credentials' => $new_encrypted ),
                    array( 'id' => $id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }

        return true;
    }
}
