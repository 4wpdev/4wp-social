<?php
/**
 * YouTube Data API v3: resumable upload, thumbnail, captions.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Api;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Low-level YouTube calls. Quota: videos.insert uses the video-upload bucket, captions.insert 400 units.
 */
final class YouTube_Client {

	private const UPLOAD_BASE = 'https://www.googleapis.com/upload/youtube/v3';

	private const API_BASE = 'https://www.googleapis.com/youtube/v3';

	/**
	 * Resumable chunk: a multiple of 256 KB (8 MB).
	 */
	public const CHUNK_SIZE = 8 * 1024 * 1024;

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
	 * Upload a video (resumable, chunked).
	 *
	 * @param string               $path     Local MP4 path.
	 * @param array<string, mixed> $video Video resource (snippet, status).
	 * @return array<string, mixed>|WP_Error Video resource with "id".
	 */
	public function upload_video( string $path, array $video ) {
		$size = (int) filesize( $path );
		if ( $size <= 0 ) {
			return new WP_Error( 'forwp_social_youtube_empty', __( 'The video file is empty.', '4wp-social' ) );
		}

		$init = Http::request(
			'POST',
			self::UPLOAD_BASE . '/videos?uploadType=resumable&part=snippet,status',
			array(
				'headers' => array(
					'Authorization'           => 'Bearer ' . $this->token,
					'Content-Type'            => 'application/json; charset=UTF-8',
					'X-Upload-Content-Length' => (string) $size,
					'X-Upload-Content-Type'   => 'video/mp4',
				),
				'body'    => (string) wp_json_encode( $video ),
			)
		);
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$session = (string) ( $init['headers']['location'] ?? '' );
		if ( 200 !== $init['code'] || '' === $session ) {
			return self::api_error( $init, __( 'YouTube did not start the upload session.', '4wp-social' ) );
		}

		$offset = 0;
		while ( $offset < $size ) {
			$length = min( self::CHUNK_SIZE, $size - $offset );
			$chunk  = Http::read_chunk( $path, $offset, $length );
			if ( is_wp_error( $chunk ) ) {
				return $chunk;
			}

			$res = Http::request(
				'PUT',
				$session,
				array(
					'timeout'     => 300,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $this->token,
						'Content-Type'  => 'video/mp4',
						'Content-Range' => sprintf( 'bytes %d-%d/%d', $offset, $offset + strlen( $chunk ) - 1, $size ),
					),
					'body'        => $chunk,
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}

			if ( in_array( $res['code'], array( 200, 201 ), true ) ) {
				return is_array( $res['json'] ) && ! empty( $res['json']['id'] )
					? $res['json']
					: new WP_Error( 'forwp_social_youtube_response', __( 'YouTube finished the upload without a video id.', '4wp-social' ) );
			}
			if ( 308 !== $res['code'] ) {
				return self::api_error( $res, __( 'YouTube upload failed.', '4wp-social' ) );
			}

			// 308 Resume Incomplete: Range "bytes=0-N" says what YouTube has.
			$offset = preg_match( '/bytes=0-(\d+)/', (string) ( $res['headers']['range'] ?? '' ), $m ) ? (int) $m[1] + 1 : 0;
		}

		return new WP_Error( 'forwp_social_youtube_incomplete', __( 'YouTube upload ended without a final response.', '4wp-social' ) );
	}

	/**
	 * Set a custom thumbnail (channel must be verified for custom thumbnails).
	 *
	 * @param string $video_id Video id.
	 * @param string $path     Local image path (JPG/PNG, ≤ 2 MB).
	 * @return true|WP_Error
	 */
	public function set_thumbnail( string $video_id, string $path ) {
		$mime = preg_match( '/\.png$/i', $path ) ? 'image/png' : 'image/jpeg';
		$body = Http::read_chunk( $path, 0, (int) filesize( $path ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$res = Http::request(
			'POST',
			self::UPLOAD_BASE . '/thumbnails/set?uploadType=media&videoId=' . rawurlencode( $video_id ),
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => $mime,
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return 200 === $res['code'] ? true : self::api_error( $res, __( 'Thumbnail upload failed.', '4wp-social' ) );
	}

	/**
	 * Upload an SRT caption track.
	 *
	 * @param string $video_id Video id.
	 * @param string $path     Local SRT path.
	 * @param string $language BCP-47 language (en, uk).
	 * @param string $name     Track name.
	 * @return true|WP_Error
	 */
	public function insert_caption( string $video_id, string $path, string $language, string $name = '' ) {
		$srt = Http::read_chunk( $path, 0, (int) filesize( $path ) );
		if ( is_wp_error( $srt ) ) {
			return $srt;
		}

		$boundary = 'forwp' . wp_generate_password( 16, false );
		$meta     = wp_json_encode(
			array(
				'snippet' => array(
					'videoId'  => $video_id,
					'language' => $language,
					'name'     => $name,
					'isDraft'  => false,
				),
			)
		);
		$body     = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
			. "--{$boundary}\r\nContent-Type: application/octet-stream\r\n\r\n{$srt}\r\n"
			. "--{$boundary}--";

		$res = Http::request(
			'POST',
			self::UPLOAD_BASE . '/captions?uploadType=multipart&part=snippet',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'multipart/related; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return 200 === $res['code'] ? true : self::api_error( $res, __( 'Caption upload failed.', '4wp-social' ) );
	}

	/**
	 * Latest uploads of the connected channel (1 quota unit per 50 videos).
	 *
	 * @param int $max Max videos.
	 * @return array<int, array{id: string, title: string, published_at: string, thumbnail: string}>|WP_Error
	 */
	public function uploads( int $max = 200 ) {
		$headers  = array( 'headers' => array( 'Authorization' => 'Bearer ' . $this->token ) );
		$channels = Http::request( 'GET', self::API_BASE . '/channels?part=contentDetails&mine=true', $headers );
		if ( is_wp_error( $channels ) ) {
			return $channels;
		}
		$playlist = (string) ( $channels['json']['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '' );
		if ( 200 !== $channels['code'] || '' === $playlist ) {
			return self::api_error( $channels, __( 'Could not read the channel uploads.', '4wp-social' ) );
		}

		$videos = array();
		$page   = '';
		do {
			$res = Http::request(
				'GET',
				self::API_BASE . '/playlistItems?part=snippet&maxResults=50&playlistId=' . rawurlencode( $playlist ) . ( '' !== $page ? '&pageToken=' . rawurlencode( $page ) : '' ),
				$headers
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			if ( 200 !== $res['code'] ) {
				return self::api_error( $res, __( 'Could not read the channel uploads.', '4wp-social' ) );
			}
			foreach ( (array) ( $res['json']['items'] ?? array() ) as $item ) {
				$snippet  = $item['snippet'] ?? array();
				$videos[] = array(
					'id'           => (string) ( $snippet['resourceId']['videoId'] ?? '' ),
					'title'        => (string) ( $snippet['title'] ?? '' ),
					'published_at' => (string) ( $snippet['publishedAt'] ?? '' ),
					'thumbnail'    => (string) ( $snippet['thumbnails']['default']['url'] ?? '' ),
				);
			}
			$page = (string) ( $res['json']['nextPageToken'] ?? '' );
		} while ( '' !== $page && count( $videos ) < $max );

		return array_slice( $videos, 0, $max );
	}

	/**
	 * One video by id (1 quota unit).
	 *
	 * @param string $video_id Video id.
	 * @return array{id: string, title: string}|WP_Error
	 */
	public function video( string $video_id ) {
		$res = Http::request(
			'GET',
			self::API_BASE . '/videos?part=snippet&id=' . rawurlencode( $video_id ),
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $this->token ) )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$item = $res['json']['items'][0] ?? null;
		if ( 200 !== $res['code'] || ! is_array( $item ) ) {
			return new WP_Error( 'forwp_social_youtube_no_video', __( 'YouTube has no video with this ID.', '4wp-social' ) );
		}

		return array(
			'id'    => (string) $item['id'],
			'title' => (string) ( $item['snippet']['title'] ?? '' ),
		);
	}

	/**
	 * Add a video to a playlist of the channel (50 quota units).
	 *
	 * @param string $video_id    Video id.
	 * @param string $playlist_id Playlist id (PL…).
	 * @return true|WP_Error
	 */
	public function add_to_playlist( string $video_id, string $playlist_id ) {
		$res = Http::json(
			'POST',
			self::API_BASE . '/playlistItems?part=snippet',
			array(
				'snippet' => array(
					'playlistId' => $playlist_id,
					'resourceId' => array(
						'kind'    => 'youtube#video',
						'videoId' => $video_id,
					),
				),
			),
			array( 'Authorization' => 'Bearer ' . $this->token )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return 200 === $res['code'] ? true : self::api_error( $res, __( 'Adding the video to the playlist failed.', '4wp-social' ) );
	}

	/**
	 * WP_Error from a Google API error body; data carries status and reason.
	 *
	 * @param array{code: int, body: string, json: array<string, mixed>|null} $res      Response.
	 * @param string                                                         $fallback Fallback text.
	 */
	private static function api_error( array $res, string $fallback ): WP_Error {
		$reason = (string) ( $res['json']['error']['errors'][0]['reason'] ?? '' );

		return new WP_Error(
			'forwp_social_youtube',
			Http::error_message( $res, $fallback ),
			array(
				'status' => (int) $res['code'],
				'reason' => $reason,
			)
		);
	}
}
