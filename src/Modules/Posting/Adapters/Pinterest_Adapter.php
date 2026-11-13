<?php
/**
 * Pinterest: image pin (sync) or video pin (register media → upload → poll → create pin).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Api\Http;
use ForWP\Social\Api\Pinterest_Client;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Image_Capable_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Pin type: meta.json "pin_type" (image|video); default video when the package has one, else image.
 * Images are sent as base64 (works from a local site); the link points to the post.
 */
final class Pinterest_Adapter implements Image_Capable_Interface {

	public const SLUG = 'pinterest.com';

	private const TITLE_MAX = 100;
	private const TEXT_MAX  = 800;
	private const ALT_MAX   = 500;

	/**
	 * Reference prefix for a video still processing on Pinterest.
	 */
	private const MEDIA_REF = 'media:';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Pinterest';
	}

	public function is_ready(): bool {
		return Credentials_Store::has( self::SLUG ) && OAuth_Controller::instance()->is_connected( self::SLUG );
	}

	public function publish( Media_Package $package ): Publish_Result {
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return Publish_Result::failed( $client->get_error_message(), false );
		}

		$board = $client->resolve_board( $this->board( $package ) );
		if ( is_wp_error( $board ) ) {
			return Publish_Result::failed( $board->get_error_message(), false );
		}

		if ( 'video' === self::pin_type( $package ) ) {
			$media = $client->register_video();
			if ( is_wp_error( $media ) ) {
				return Publish_Result::failed( $media->get_error_message() );
			}
			$uploaded = $client->upload_video( $media['upload_url'], $media['upload_parameters'], $package->video_path() );
			if ( is_wp_error( $uploaded ) ) {
				return Publish_Result::failed( $uploaded->get_error_message() );
			}

			return Publish_Result::processing( self::MEDIA_REF . $media['media_id'], __( 'Video uploaded, Pinterest is processing it.', '4wp-social' ) );
		}

		$image = self::image_source( $package->cover_path() );
		if ( is_wp_error( $image ) ) {
			return Publish_Result::failed( $image->get_error_message(), false );
		}

		return $this->create( $client, $package, $board, $image );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		if ( 0 !== strpos( $reference, self::MEDIA_REF ) ) {
			return Publish_Result::published( $reference, self::pin_url( $reference ) );
		}

		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return Publish_Result::failed( $client->get_error_message(), false );
		}

		$media_id = substr( $reference, strlen( self::MEDIA_REF ) );
		$status   = $client->media_status( $media_id );
		if ( is_wp_error( $status ) ) {
			return Publish_Result::failed( $status->get_error_message() );
		}

		switch ( (string) ( $status['status'] ?? '' ) ) {
			case 'succeeded':
				$board = $client->resolve_board( $this->board( $package ) );
				if ( is_wp_error( $board ) ) {
					return Publish_Result::failed( $board->get_error_message(), false );
				}
				$source = array(
					'source_type' => 'video_id',
					'media_id'    => $media_id,
				);
				$cover  = self::image_source( $package->cover_path() );
				if ( ! is_wp_error( $cover ) ) {
					$source['cover_image_content_type'] = $cover['content_type'];
					$source['cover_image_data']         = $cover['data'];
				} else {
					$source['cover_image_key_frame_time'] = $package->cover_ms();
				}

				return $this->create( $client, $package, $board, $source );

			case 'failed':
				return Publish_Result::failed( __( 'Pinterest could not process the video.', '4wp-social' ), false );

			default:
				return Publish_Result::processing( $reference, (string) ( $status['status'] ?? '' ) );
		}
	}

	/**
	 * Pin type for the package.
	 *
	 * @param Media_Package $package Package.
	 */
	public static function pin_type( Media_Package $package ): string {
		$wanted = sanitize_key( (string) ( $package->entry()['pin_type'] ?? '' ) );
		if ( 'image' === $wanted || ! $package->has_video() ) {
			return 'image';
		}

		return 'video';
	}

	/**
	 * Description: package description (or title) + hashtags, Pinterest limit.
	 *
	 * @param Media_Package $package Package.
	 */
	public static function description( Media_Package $package ): string {
		$text = '' !== trim( $package->description() ) ? $package->description() : $package->title();

		$tags = array();
		foreach ( $package->tags() as $tag ) {
			$tag = preg_replace( '/[^\p{L}\p{N}_]/u', '', $tag );
			if ( '' !== $tag && false === stripos( $text, '#' . $tag ) ) {
				$tags[] = '#' . $tag;
			}
		}
		if ( $tags ) {
			$text = trim( $text ) . "\n\n" . implode( ' ', $tags );
		}

		return self::cut( $text, self::TEXT_MAX );
	}

	/**
	 * Image file → media_source for an image pin.
	 *
	 * @param string $path Image path.
	 * @return array{source_type: string, content_type: string, data: string}|WP_Error
	 */
	public static function image_source( string $path ) {
		if ( '' === $path || ! is_readable( $path ) ) {
			return new WP_Error( 'forwp_social_pinterest_image', __( 'No image for the pin: add a cover to the package or a featured image to the post.', '4wp-social' ) );
		}
		$type = (string) ( wp_check_filetype( $path )['type'] ?? '' );
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png' ), true ) ) {
			return new WP_Error( 'forwp_social_pinterest_image', __( 'Pinterest accepts JPEG or PNG images.', '4wp-social' ) );
		}
		$data = Http::read_chunk( $path, 0, (int) filesize( $path ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array(
			'source_type'  => 'image_base64',
			'content_type' => $type,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Pinterest image_base64 source.
			'data'         => base64_encode( $data ),
		);
	}

	/**
	 * Create the pin and map the result.
	 *
	 * @param Pinterest_Client     $client  Client.
	 * @param Media_Package        $package Package.
	 * @param string               $board   Board id.
	 * @param array<string, mixed> $source  media_source.
	 */
	private function create( Pinterest_Client $client, Media_Package $package, string $board, array $source ): Publish_Result {
		$link = (string) get_permalink( $package->post_id() );
		$pin  = array(
			'board_id'     => $board,
			'title'        => self::cut( $package->title(), self::TITLE_MAX ),
			'description'  => self::description( $package ),
			'alt_text'     => self::cut( $package->title(), self::ALT_MAX ),
			'media_source' => $source,
		);
		if ( '' !== $link ) {
			$pin['link'] = $link;
		}

		$res = $client->create_pin( $pin );
		if ( is_wp_error( $res ) ) {
			$status = (int) ( $res->get_error_data()['status'] ?? 0 );
			return Publish_Result::failed( $res->get_error_message(), 429 === $status || $status >= 500 );
		}

		$id = (string) ( $res['id'] ?? '' );
		if ( '' === $id ) {
			return Publish_Result::failed( __( 'Pinterest did not return a pin id.', '4wp-social' ) );
		}

		return Publish_Result::published( $id, self::pin_url( $id ), $this->sandbox() ? __( 'Sandbox pin (Trial access): visible only to you.', '4wp-social' ) : '' );
	}

	/**
	 * Board: meta.json "board", else the account setting.
	 *
	 * @param Media_Package $package Package.
	 */
	private function board( Media_Package $package ): string {
		$entry = (string) ( $package->entry()['board'] ?? '' );

		return '' !== trim( $entry ) ? $entry : Credentials_Store::extra( self::SLUG, 'board' );
	}

	/**
	 * Sandbox mode from the account setting.
	 */
	private function sandbox(): bool {
		return '1' === Credentials_Store::extra( self::SLUG, 'sandbox' );
	}

	/**
	 * Client with a fresh token.
	 *
	 * @return Pinterest_Client|WP_Error
	 */
	private function client() {
		$token = OAuth_Controller::instance()->get_access_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return new Pinterest_Client( $token, $this->sandbox() );
	}

	/**
	 * Public pin URL.
	 *
	 * @param string $id Pin id.
	 */
	private static function pin_url( string $id ): string {
		return 'https://www.pinterest.com/pin/' . rawurlencode( $id ) . '/';
	}

	/**
	 * Cut to a length (multibyte safe).
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 */
	private static function cut( string $text, int $max ): string {
		$text = trim( $text );

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}
}
