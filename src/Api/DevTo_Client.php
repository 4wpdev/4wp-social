<?php
/**
 * DEV.to (Forem API v1): account and articles.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates with a personal API key.
 */
final class DevTo_Client {

	public const BASE = 'https://dev.to/api';

	/**
	 * @var string
	 */
	private $api_key;

	/**
	 * @param string $api_key API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = trim( $api_key );
	}

	/**
	 * Account of the API key.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function me() {
		return $this->expect(
			Http::request( 'GET', self::BASE . '/users/me', array( 'headers' => $this->headers() ) ),
			__( 'DEV.to rejected the API key.', '4wp-social' )
		);
	}

	/**
	 * @param array<string, mixed> $article Article fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_article( array $article ) {
		return $this->expect(
			Http::json( 'POST', self::BASE . '/articles', array( 'article' => $article ), $this->headers() ),
			__( 'DEV.to did not accept the article.', '4wp-social' )
		);
	}

	/**
	 * @param int                  $id      Article id.
	 * @param array<string, mixed> $article Article fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_article( int $id, array $article ) {
		return $this->expect(
			Http::json( 'PUT', self::BASE . '/articles/' . $id, array( 'article' => $article ), $this->headers() ),
			__( 'DEV.to did not accept the article.', '4wp-social' )
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array(
			'api-key' => $this->api_key,
			'Accept'  => 'application/vnd.forem.api-v1+json',
		);
	}

	/**
	 * JSON body of a 2xx response, else an error carrying the HTTP status.
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

		return new WP_Error( 'forwp_social_devto', Http::error_message( $response, $fallback ), array( 'status' => $response['code'] ) );
	}
}
