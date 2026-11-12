<?php
/**
 * Meta Graph API: Facebook Page Reels (start → upload → finish → status).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use ForWP\Social\Auth\Meta_OAuth_Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Low-level calls with a Page access token.
 */
final class Meta_Client {

	private const RUPLOAD_URL = 'https://rupload.facebook.com/video-upload/';

	/**
	 * Instagram upload attempts and pause between them (seconds).
	 */
	private const IG_UPLOAD_TRIES = 4;
	private const IG_UPLOAD_PAUSE = 5;

	/**
	 * Page access token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * @param string $page_token Page access token.
	 */
	public function __construct( string $page_token ) {
		$this->token = $page_token;
	}

	/**
	 * Start a Reel upload session.
	 *
	 * @param string $page_id Page ID.
	 * @return array{video_id: string, upload_url: string}|WP_Error
	 */
	public function reel_start( string $page_id ) {
		$res = Meta_OAuth_Provider::graph( 'POST', $page_id . '/video_reels', array( 'upload_phase' => 'start' ), $this->token );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['video_id'] ) ) {
			return new WP_Error( 'forwp_social_meta_start', __( 'Facebook did not return a video ID.', '4wp-social' ) );
		}

		return array(
			'video_id'   => (string) $res['video_id'],
			'upload_url' => (string) ( $res['upload_url'] ?? '' ),
		);
	}

	/**
	 * Upload the local file in one request.
	 *
	 * @param string $video_id   Video ID from reel_start().
	 * @param string $path       Local file.
	 * @param string $upload_url Upload URL from reel_start() (empty = build it).
	 * @return true|WP_Error
	 */
	public function reel_upload( string $video_id, string $path, string $upload_url = '' ) {
		$size = (int) filesize( $path );
		$data = Http::read_chunk( $path, 0, $size );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$url = '' !== $upload_url ? $upload_url : self::RUPLOAD_URL . Meta_OAuth_Provider::GRAPH_VERSION . '/' . rawurlencode( $video_id );
		$res = Http::request(
			'POST',
			$url,
			array(
				'timeout' => 600,
				'headers' => array(
					'Authorization' => 'OAuth ' . $this->token,
					'offset'        => '0',
					'file_size'     => (string) $size,
					'Content-Type'  => 'video/mp4',
				),
				'body'    => $data,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] || empty( $res['json']['success'] ) ) {
			$debug = (string) ( $res['json']['debug_info']['message'] ?? '' );
			return new WP_Error( 'forwp_social_meta_upload', '' !== $debug ? __( 'Meta upload failed:', '4wp-social' ) . ' ' . $debug : Http::error_message( $res, __( 'Meta upload failed.', '4wp-social' ) ) );
		}

		return true;
	}

	/**
	 * Finish the upload and publish (or save as draft).
	 *
	 * @param string $page_id     Page ID.
	 * @param string $video_id    Video ID.
	 * @param string $description Caption.
	 * @param string $state       PUBLISHED|DRAFT.
	 * @return true|WP_Error
	 */
	public function reel_finish( string $page_id, string $video_id, string $description, string $state ) {
		$res = Meta_OAuth_Provider::graph(
			'POST',
			$page_id . '/video_reels',
			array(
				'upload_phase' => 'finish',
				'video_id'     => $video_id,
				'video_state'  => $state,
				'description'  => $description,
			),
			$this->token
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['success'] ) ) {
			return new WP_Error( 'forwp_social_meta_finish', __( 'Facebook did not accept the Reel.', '4wp-social' ) );
		}

		return true;
	}

	/**
	 * Instagram: create a Reels container for a resumable upload.
	 *
	 * @param string $ig_id   Instagram professional account ID.
	 * @param string $caption Caption.
	 * @return array{id: string, uri: string}|WP_Error
	 */
	public function ig_reel_container( string $ig_id, string $caption ) {
		$res = Meta_OAuth_Provider::graph(
			'POST',
			$ig_id . '/media',
			array(
				'media_type'    => 'REELS',
				'upload_type'   => 'resumable',
				'caption'       => $caption,
				'share_to_feed' => 'true',
			),
			$this->token
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['id'] ) ) {
			return new WP_Error( 'forwp_social_ig_container', __( 'Instagram did not return a container ID.', '4wp-social' ) );
		}

		return array(
			'id'  => (string) $res['id'],
			'uri' => (string) ( $res['uri'] ?? '' ),
		);
	}

	/**
	 * Instagram: upload the local file to the container (rupload, one request).
	 *
	 * @param string $container_id Container ID.
	 * @param string $path         Local file.
	 * @param string $uri          Upload URI from ig_reel_container() (empty = build it).
	 * @return true|WP_Error
	 */
	public function ig_upload( string $container_id, string $path, string $uri = '' ) {
		$url = '' !== $uri ? $uri : 'https://rupload.facebook.com/ig-api-upload/' . Meta_OAuth_Provider::GRAPH_VERSION . '/' . rawurlencode( $container_id );

		// A fresh container rejects the first upload ("Request processing failed"); a retry seconds later succeeds.
		$result = true;
		for ( $try = 1; $try <= self::IG_UPLOAD_TRIES; $try++ ) {
			$result = $this->reel_upload( $container_id, $path, $url );
			if ( true === $result ) {
				return true;
			}
			if ( $try < self::IG_UPLOAD_TRIES ) {
				sleep( self::IG_UPLOAD_PAUSE );
			}
		}

		return $result;
	}

	/**
	 * Instagram: container status_code (IN_PROGRESS, FINISHED, ERROR, EXPIRED, PUBLISHED).
	 *
	 * @param string $container_id Container ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function ig_container_status( string $container_id ) {
		return Meta_OAuth_Provider::graph( 'GET', $container_id, array( 'fields' => 'status_code,status' ), $this->token );
	}

	/**
	 * Instagram: publish a finished container.
	 *
	 * @param string $ig_id        Instagram account ID.
	 * @param string $container_id Container ID.
	 * @return string|WP_Error Media ID.
	 */
	public function ig_publish( string $ig_id, string $container_id ) {
		$res = Meta_OAuth_Provider::graph( 'POST', $ig_id . '/media_publish', array( 'creation_id' => $container_id ), $this->token );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return empty( $res['id'] ) ? new WP_Error( 'forwp_social_ig_publish', __( 'Instagram did not return a media ID.', '4wp-social' ) ) : (string) $res['id'];
	}

	/**
	 * Instagram: public link of a media.
	 *
	 * @param string $media_id Media ID.
	 */
	public function ig_permalink( string $media_id ): string {
		$res = Meta_OAuth_Provider::graph( 'GET', $media_id, array( 'fields' => 'permalink' ), $this->token );

		return is_wp_error( $res ) ? '' : (string) ( $res['permalink'] ?? '' );
	}

	/**
	 * Processing and publishing status of a video.
	 *
	 * @param string $video_id Video ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function video_status( string $video_id ) {
		return Meta_OAuth_Provider::graph( 'GET', $video_id, array( 'fields' => 'status,permalink_url' ), $this->token );
	}
}
