<?php
/**
 * TikTok Content Posting API (Direct Post, FILE_UPLOAD).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Low-level TikTok calls. Init is limited to 6 requests per minute per token.
 */
final class TikTok_Client {

	private const API_BASE = 'https://open.tiktokapis.com/v2/post/publish';

	/**
	 * Upload chunk size above the single-chunk limit (TikTok allows 5–64 MB chunks).
	 */
	public const CHUNK_SIZE = 10 * 1024 * 1024;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @param string $access_token OAuth access token.
	 */
	public function __construct( string $access_token ) {
		$this->token = $access_token;
	}

	/**
	 * Chunk plan for a file: whole file up to CHUNK_SIZE, otherwise floor(size / CHUNK_SIZE)
	 * chunks with the remainder added to the last one (TikTok rule).
	 *
	 * @param int $size File size in bytes.
	 * @return array{chunk_size: int, total_chunk_count: int}
	 */
	public static function chunk_plan( int $size ): array {
		if ( $size <= self::CHUNK_SIZE ) {
			return array(
				'chunk_size'        => $size,
				'total_chunk_count' => 1,
			);
		}

		return array(
			'chunk_size'        => self::CHUNK_SIZE,
			'total_chunk_count' => (int) floor( $size / self::CHUNK_SIZE ),
		);
	}

	/**
	 * Creator info: privacy options, limits, username.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function creator_info() {
		return $this->post( '/creator_info/query/', array() );
	}

	/**
	 * Init a Direct Post with FILE_UPLOAD.
	 *
	 * @param array<string, mixed> $post_info Post info (title, privacy_level, …).
	 * @param int                  $size      Video size in bytes.
	 * @return array<string, mixed>|WP_Error data: publish_id, upload_url.
	 */
	public function init_upload( array $post_info, int $size ) {
		$plan = self::chunk_plan( $size );

		return $this->post(
			'/video/init/',
			array(
				'post_info'   => $post_info,
				'source_info' => array(
					'source'            => 'FILE_UPLOAD',
					'video_size'        => $size,
					'chunk_size'        => $plan['chunk_size'],
					'total_chunk_count' => $plan['total_chunk_count'],
				),
			)
		);
	}

	/**
	 * PUT the file to the upload URL, chunk by chunk.
	 *
	 * @param string $upload_url URL from init (valid 1 hour).
	 * @param string $path       Local MP4 path.
	 * @return true|WP_Error
	 */
	public function upload_file( string $upload_url, string $path ) {
		$size = (int) filesize( $path );
		$plan = self::chunk_plan( $size );

		for ( $i = 0; $i < $plan['total_chunk_count']; $i++ ) {
			$start  = $i * $plan['chunk_size'];
			$last   = $i === $plan['total_chunk_count'] - 1;
			$length = $last ? $size - $start : $plan['chunk_size'];
			$chunk  = Http::read_chunk( $path, $start, $length );
			if ( is_wp_error( $chunk ) ) {
				return $chunk;
			}

			$res = Http::request(
				'PUT',
				$upload_url,
				array(
					'timeout' => 300,
					'headers' => array(
						'Content-Type'  => 'video/mp4',
						'Content-Range' => sprintf( 'bytes %d-%d/%d', $start, $start + strlen( $chunk ) - 1, $size ),
					),
					'body'    => $chunk,
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			if ( ! in_array( $res['code'], array( 200, 201, 206 ), true ) ) {
				return new WP_Error(
					'forwp_social_tiktok_upload',
					Http::error_message( $res, __( 'TikTok upload failed.', '4wp-social' ) ),
					array( 'status' => $res['code'] )
				);
			}
		}

		return true;
	}

	/**
	 * Publish status.
	 *
	 * @param string $publish_id Publish id from init.
	 * @return array<string, mixed>|WP_Error data: status, fail_reason, publicaly_available_post_id.
	 */
	public function fetch_status( string $publish_id ) {
		return $this->post( '/status/fetch/', array( 'publish_id' => $publish_id ) );
	}

	/**
	 * POST JSON and unwrap {data, error}.
	 *
	 * @param string               $path    Path under the publish API.
	 * @param array<string, mixed> $payload Body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function post( string $path, array $payload ) {
		$res = Http::json( 'POST', self::API_BASE . $path, $payload, array( 'Authorization' => 'Bearer ' . $this->token ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$error = is_array( $res['json']['error'] ?? null ) ? $res['json']['error'] : array();
		$code  = (string) ( $error['code'] ?? '' );
		if ( 200 !== $res['code'] || ( '' !== $code && 'ok' !== $code ) ) {
			return new WP_Error(
				'forwp_social_tiktok',
				Http::error_message( $res, __( 'TikTok API request failed.', '4wp-social' ) ),
				array(
					'status' => (int) $res['code'],
					'reason' => $code,
				)
			);
		}

		return is_array( $res['json']['data'] ?? null ) ? $res['json']['data'] : array();
	}
}
