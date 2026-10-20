<?php
/**
 * Thin wrapper over the WordPress HTTP API.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends requests and decodes JSON.
 */
final class Http {

	/**
	 * Send a request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     URL.
	 * @param array<string, mixed> $args    wp_remote_request args (headers, body, timeout).
	 * @return array{code: int, headers: array<string, string>, body: string, json: array<string, mixed>|null}|WP_Error
	 */
	public static function request( string $method, string $url, array $args = array() ) {
		$args = array_merge(
			array(
				'timeout' => 30,
				'headers' => array(),
			),
			$args
		);

		$args['method'] = $method;

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$json    = json_decode( $body, true );
		$headers = array();
		// WP returns a CaseInsensitiveDictionary object: (array) on it yields its private props, not the headers.
		$raw = wp_remote_retrieve_headers( $response );
		$raw = is_object( $raw ) && method_exists( $raw, 'getAll' ) ? $raw->getAll() : (array) $raw;
		foreach ( $raw as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? (string) end( $value ) : (string) $value;
		}

		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => $headers,
			'body'    => $body,
			'json'    => is_array( $json ) ? $json : null,
		);
	}

	/**
	 * Send JSON and expect JSON.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     URL.
	 * @param array<string, mixed> $payload JSON body.
	 * @param array<string, mixed> $headers Extra headers.
	 * @return array{code: int, headers: array<string, string>, body: string, json: array<string, mixed>|null}|WP_Error
	 */
	public static function json( string $method, string $url, array $payload, array $headers = array() ) {
		return self::request(
			$method,
			$url,
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/json; charset=UTF-8' ), $headers ),
				'body'    => (string) wp_json_encode( $payload ),
			)
		);
	}

	/**
	 * Read a byte range of a local file.
	 *
	 * @param string $path   File path.
	 * @param int    $offset Start byte.
	 * @param int    $length Bytes to read.
	 * @return string|WP_Error
	 */
	public static function read_chunk( string $path, int $offset, int $length ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Chunked binary read; WP_Filesystem has no ranged read.
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'forwp_social_read', __( 'Could not open the media file.', '4wp-social' ) );
		}

		fseek( $handle, $offset );
		$data = '';
		$read = 0;
		while ( $read < $length && ! feof( $handle ) ) {
			$part = fread( $handle, $length - $read );
			if ( false === $part || '' === $part ) {
				break;
			}
			$data .= $part;
			$read += strlen( $part );
		}
		fclose( $handle );
		// phpcs:enable

		return $data;
	}

	/**
	 * Error message from a JSON error body (Google: error.message, TikTok: error.message / error_description).
	 *
	 * @param array{code: int, body: string, json: array<string, mixed>|null} $response Response.
	 * @param string                                                         $fallback Fallback text.
	 */
	public static function error_message( array $response, string $fallback ): string {
		$json = is_array( $response['json'] ) ? $response['json'] : array();
		$err  = $json['error'] ?? null;

		if ( is_array( $err ) && ! empty( $err['message'] ) ) {
			return (string) $err['message'];
		}
		if ( ! empty( $json['error_description'] ) ) {
			return (string) $json['error_description'];
		}
		if ( is_string( $err ) && '' !== $err ) {
			return $err;
		}

		return sprintf( '%s (HTTP %d)', $fallback, (int) $response['code'] );
	}
}
