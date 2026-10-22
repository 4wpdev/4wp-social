<?php
/**
 * Bluesky (AT Protocol) XRPC: session, blob upload, post record.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * App-password session against the PDS entryway (bsky.social proxies to the user's PDS).
 */
final class Bluesky_Client {

	public const SERVICE = 'https://bsky.social';

	/**
	 * Blob limit for video on Bluesky.
	 */
	public const VIDEO_MAX_BYTES = 100 * 1048576;

	/**
	 * @var string
	 */
	private $service;

	/**
	 * @var string
	 */
	private $access_jwt = '';

	/**
	 * @var string
	 */
	private $did = '';

	/**
	 * @param string $service PDS base URL.
	 */
	public function __construct( string $service = '' ) {
		/**
		 * Filter the Bluesky PDS base URL (self-hosted PDS).
		 *
		 * @param string $service Base URL.
		 */
		$this->service = untrailingslashit( (string) apply_filters( 'forwp_social_bluesky_service', '' !== $service ? $service : self::SERVICE ) );
	}

	/**
	 * Log in with handle (or email) and app password.
	 *
	 * @param string $identifier Handle or email.
	 * @param string $password   App password.
	 * @return array{did: string, handle: string}|WP_Error
	 */
	public function login( string $identifier, string $password ) {
		$response = Http::json(
			'POST',
			$this->service . '/xrpc/com.atproto.server.createSession',
			array(
				'identifier' => ltrim( trim( $identifier ), '@' ),
				'password'   => trim( $password ),
			)
		);
		$json = $this->expect( $response, __( 'Bluesky login failed.', '4wp-social' ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$this->access_jwt = (string) ( $json['accessJwt'] ?? '' );
		$this->did        = (string) ( $json['did'] ?? '' );
		if ( '' === $this->access_jwt || '' === $this->did ) {
			return new WP_Error( 'forwp_social_bluesky', __( 'Bluesky returned no session.', '4wp-social' ) );
		}

		return array(
			'did'    => $this->did,
			'handle' => (string) ( $json['handle'] ?? '' ),
		);
	}

	/**
	 * Upload a local file as a blob.
	 *
	 * @param string $path File path.
	 * @param string $mime Mime type.
	 * @return array<string, mixed>|WP_Error Blob reference for embeds.
	 */
	public function upload_blob( string $path, string $mime ) {
		$size = (int) filesize( $path );
		$data = Http::read_chunk( $path, 0, $size );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$response = Http::request(
			'POST',
			$this->service . '/xrpc/com.atproto.repo.uploadBlob',
			array(
				'timeout' => 300,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->access_jwt,
					'Content-Type'  => $mime,
				),
				'body'    => $data,
			)
		);
		$json = $this->expect( $response, __( 'Bluesky upload failed.', '4wp-social' ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		return is_array( $json['blob'] ?? null ) ? $json['blob'] : new WP_Error( 'forwp_social_bluesky', __( 'Bluesky returned no blob.', '4wp-social' ) );
	}

	/**
	 * Create an app.bsky.feed.post record.
	 *
	 * @param array<string, mixed> $record Post record without $type.
	 * @return array{uri: string, rkey: string}|WP_Error
	 */
	public function create_post( array $record ) {
		$response = Http::json(
			'POST',
			$this->service . '/xrpc/com.atproto.repo.createRecord',
			array(
				'repo'       => $this->did,
				'collection' => 'app.bsky.feed.post',
				'record'     => array_merge( array( '$type' => 'app.bsky.feed.post' ), $record ),
			),
			array( 'Authorization' => 'Bearer ' . $this->access_jwt )
		);
		$json = $this->expect( $response, __( 'Bluesky post failed.', '4wp-social' ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$uri = (string) ( $json['uri'] ?? '' );

		return array(
			'uri'  => $uri,
			'rkey' => self::rkey( $uri ),
		);
	}

	/**
	 * Rich-text facets for links and hashtags (offsets are UTF-8 bytes).
	 *
	 * @param string $text Post text.
	 * @return array<int, array<string, mixed>>
	 */
	public static function facets( string $text ): array {
		$facets = array();

		if ( preg_match_all( '#https?://[^\s<>"]+#u', $text, $links, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $links[0] as $match ) {
				$url      = rtrim( (string) $match[0], '.,;:!?)]\'' );
				$facets[] = self::facet( (int) $match[1], strlen( $url ), array( '$type' => 'app.bsky.richtext.facet#link', 'uri' => $url ) );
			}
		}

		if ( preg_match_all( '/(?<![\p{L}\p{N}_#\/])#([\p{L}\p{N}_]*\p{L}[\p{L}\p{N}_]*)/u', $text, $tags, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $tags[0] as $i => $match ) {
				$facets[] = self::facet( (int) $match[1], strlen( (string) $match[0] ), array( '$type' => 'app.bsky.richtext.facet#tag', 'tag' => (string) $tags[1][ $i ][0] ) );
			}
		}

		return $facets;
	}

	/**
	 * Record key from an at:// URI.
	 *
	 * @param string $uri at://did/app.bsky.feed.post/rkey.
	 */
	public static function rkey( string $uri ): string {
		$parts = explode( '/', $uri );

		return (string) end( $parts );
	}

	/**
	 * @param int                  $start   Byte start.
	 * @param int                  $length  Byte length.
	 * @param array<string, mixed> $feature Facet feature.
	 * @return array<string, mixed>
	 */
	private static function facet( int $start, int $length, array $feature ): array {
		return array(
			'index'    => array(
				'byteStart' => $start,
				'byteEnd'   => $start + $length,
			),
			'features' => array( $feature ),
		);
	}

	/**
	 * JSON body of a 2xx response, else an error with the XRPC message.
	 *
	 * @param array<string, mixed>|WP_Error $response Response.
	 * @param string                        $fallback Fallback text.
	 * @return array<string, mixed>|WP_Error
	 */
	private function expect( $response, string $fallback ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response['code'] >= 200 && $response['code'] < 300 && is_array( $response['json'] ) ) {
			return $response['json'];
		}

		$json    = is_array( $response['json'] ) ? $response['json'] : array();
		$message = ! empty( $json['message'] ) ? (string) $json['message'] : Http::error_message( $response, $fallback );

		return new WP_Error( 'forwp_social_bluesky', $message, array( 'reason' => (string) ( $json['error'] ?? '' ), 'status' => $response['code'] ) );
	}
}
