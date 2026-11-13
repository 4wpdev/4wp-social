<?php
/**
 * Pinterest API v5: account, boards, image pins, video upload (media) and video pins.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Writes go to api-sandbox.pinterest.com when $sandbox is true (apps with Trial access).
 */
final class Pinterest_Client {

	private const API     = 'https://api.pinterest.com/v5';
	private const SANDBOX = 'https://api-sandbox.pinterest.com/v5';

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Use the sandbox host.
	 *
	 * @var bool
	 */
	private $sandbox;

	/**
	 * Client.
	 *
	 * @param string $token   Access token.
	 * @param bool   $sandbox Sandbox host for pins and media.
	 */
	public function __construct( string $token, bool $sandbox ) {
		$this->token   = $token;
		$this->sandbox = $sandbox;
	}

	/**
	 * GET /user_account (always production: it only reads).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function user_account() {
		return $this->call( 'GET', self::API . '/user_account' );
	}

	/**
	 * Boards of the account (first 100).
	 *
	 * @return array<int, array{id: string, name: string}>|WP_Error
	 */
	public function boards() {
		$res = $this->call( 'GET', $this->base() . '/boards?page_size=100' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$out = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $board ) {
			if ( ! empty( $board['id'] ) ) {
				$out[] = array(
					'id'   => (string) $board['id'],
					'name' => (string) ( $board['name'] ?? '' ),
				);
			}
		}

		return $out;
	}

	/**
	 * Board id from an id or a name; the first board when $wanted is empty.
	 *
	 * @param string $wanted Board id or name.
	 * @return string|WP_Error
	 */
	public function resolve_board( string $wanted ) {
		$boards = $this->boards();
		if ( is_wp_error( $boards ) ) {
			return $boards;
		}
		if ( ! $boards ) {
			return new WP_Error( 'forwp_social_pinterest_board', __( 'The Pinterest account has no boards. Create one first.', '4wp-social' ) );
		}

		$wanted = trim( $wanted );
		if ( '' === $wanted ) {
			return $boards[0]['id'];
		}
		foreach ( $boards as $board ) {
			if ( $board['id'] === $wanted || 0 === strcasecmp( $board['name'], $wanted ) ) {
				return $board['id'];
			}
		}

		/* translators: %s: board name or id. */
		return new WP_Error( 'forwp_social_pinterest_board', sprintf( __( 'Pinterest board "%s" not found.', '4wp-social' ), $wanted ) );
	}

	/**
	 * POST /pins.
	 *
	 * @param array<string, mixed> $pin Pin body (board_id, title, description, link, alt_text, media_source).
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_pin( array $pin ) {
		return $this->call( 'POST', $this->base() . '/pins', $pin );
	}

	/**
	 * POST /media: register a video upload.
	 *
	 * @return array{media_id: string, upload_url: string, upload_parameters: array<string, string>}|WP_Error
	 */
	public function register_video() {
		$res = $this->call( 'POST', $this->base() . '/media', array( 'media_type' => 'video' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['media_id'] ) || empty( $res['upload_url'] ) ) {
			return new WP_Error( 'forwp_social_pinterest_media', __( 'Pinterest did not return an upload URL.', '4wp-social' ) );
		}

		return array(
			'media_id'          => (string) $res['media_id'],
			'upload_url'        => (string) $res['upload_url'],
			'upload_parameters' => array_map( 'strval', (array) ( $res['upload_parameters'] ?? array() ) ),
		);
	}

	/**
	 * Upload the file to the pre-signed URL: multipart form with the given parameters, then the file.
	 *
	 * @param string                $url    Upload URL.
	 * @param array<string, string> $params Upload parameters.
	 * @param string                $path   Video path.
	 * @return true|WP_Error
	 */
	public function upload_video( string $url, array $params, string $path ) {
		$data = Http::read_chunk( $path, 0, (int) filesize( $path ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$boundary = 'forwp' . wp_generate_password( 24, false );
		$body     = '';
		foreach ( $params as $name => $value ) {
			$body .= '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n" . $value . "\r\n";
		}
		$body .= '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="file"; filename="' . basename( $path ) . '"' . "\r\n" . 'Content-Type: video/mp4' . "\r\n\r\n" . $data . "\r\n";
		$body .= '--' . $boundary . "--\r\n";

		$res = Http::request(
			'POST',
			$url,
			array(
				'timeout' => 300,
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			/* translators: %d: HTTP status. */
			return new WP_Error( 'forwp_social_pinterest_upload', sprintf( __( 'Pinterest video upload failed (HTTP %d).', '4wp-social' ), $res['code'] ) );
		}

		return true;
	}

	/**
	 * GET /media/{id}: status registered | processing | succeeded | failed.
	 *
	 * @param string $media_id Media id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function media_status( string $media_id ) {
		return $this->call( 'GET', $this->base() . '/media/' . rawurlencode( $media_id ) );
	}

	/**
	 * Host for pins, boards and media.
	 */
	private function base(): string {
		return $this->sandbox ? self::SANDBOX : self::API;
	}

	/**
	 * JSON request with the bearer token.
	 *
	 * @param string                    $method  Method.
	 * @param string                    $url     URL.
	 * @param array<string, mixed>|null $payload JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function call( string $method, string $url, ?array $payload = null ) {
		$res = Http::request(
			$method,
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'body'    => null === $payload ? null : (string) wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			$json    = is_array( $res['json'] ) ? $res['json'] : array();
			$message = ! empty( $json['message'] ) ? (string) $json['message'] : Http::error_message( $res, __( 'Pinterest API error.', '4wp-social' ) );

			return new WP_Error( 'forwp_social_pinterest', $message, array( 'status' => $res['code'] ) );
		}

		return is_array( $res['json'] ) ? $res['json'] : array();
	}
}
