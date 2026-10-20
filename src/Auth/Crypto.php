<?php
/**
 * Encrypt/decrypt sensitive option values (same scheme as 4WP Drive).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Site-salt encryption helper.
 */
final class Crypto {

	/**
	 * @param string $plain Plaintext.
	 */
	public static function encrypt( string $plain ): string {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
			$enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $enc ) {
				return base64_encode( $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}

		return base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $encoded Encrypted payload.
	 */
	public static function decrypt( string $encoded ): ?string {
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return null;
		}

		if ( function_exists( 'openssl_decrypt' ) ) {
			$key = hash( 'sha256', wp_salt( 'auth' ), true );
			$iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 16 );
			$dec = openssl_decrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $dec ) {
				return $dec;
			}
		}

		return $raw;
	}

	/**
	 * Store an array as an encrypted option.
	 *
	 * @param string               $option Option name.
	 * @param array<string, mixed> $data   Data.
	 */
	public static function save_option( string $option, array $data ): void {
		$json = wp_json_encode( $data );
		if ( is_string( $json ) ) {
			update_option( $option, self::encrypt( $json ), false );
		}
	}

	/**
	 * Read an encrypted array option.
	 *
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	public static function read_option( string $option ): array {
		$stored = get_option( $option, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return array();
		}

		$json = self::decrypt( $stored );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;

		return is_array( $data ) ? $data : array();
	}
}
