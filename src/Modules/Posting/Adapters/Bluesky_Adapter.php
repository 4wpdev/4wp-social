<?php
/**
 * Bluesky: login → upload video blob → create post with a video embed.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Api\Bluesky_Client;
use ForWP\Social\Auth\Bluesky_Account;
use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Synchronous: the post exists as soon as createRecord returns; Bluesky processes the video afterwards.
 * Bluesky has no private or unlisted posts, so only "public" packages are published.
 */
final class Bluesky_Adapter implements Platform_Adapter_Interface {

	public const SLUG = Bluesky_Account::SLUG;

	/**
	 * Post text limit (graphemes).
	 */
	private const TEXT_MAX = 300;

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Bluesky';
	}

	public function is_ready(): bool {
		return Bluesky_Account::is_connected();
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $package->has_video() ) {
			return Publish_Result::failed( __( 'No video file to upload.', '4wp-social' ), false );
		}
		if ( 'public' !== $package->privacy() ) {
			return Publish_Result::failed( __( 'Bluesky posts are always public; set privacy to public to post there.', '4wp-social' ), false );
		}
		if ( filesize( $package->video_path() ) > Bluesky_Client::VIDEO_MAX_BYTES ) {
			return Publish_Result::failed( __( 'The video is larger than the Bluesky limit of 100 MB.', '4wp-social' ), false );
		}

		$client = Bluesky_Account::client();
		if ( is_wp_error( $client ) ) {
			return Publish_Result::failed( $client->get_error_message(), false );
		}

		$blob = $client->upload_blob( $package->video_path(), 'video/mp4' );
		if ( is_wp_error( $blob ) ) {
			return Publish_Result::failed( $blob->get_error_message() );
		}

		$embed = array(
			'$type' => 'app.bsky.embed.video',
			'video' => $blob,
			'alt'   => $package->title(),
		);
		$ratio = self::aspect_ratio( $package->video_path() );
		if ( $ratio ) {
			$embed['aspectRatio'] = $ratio;
		}

		$text   = self::text( $package );
		$record = array(
			'text'      => $text,
			'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
			'embed'     => $embed,
		);
		$facets = Bluesky_Client::facets( $text );
		if ( $facets ) {
			$record['facets'] = $facets;
		}

		$post = $client->create_post( $record );
		if ( is_wp_error( $post ) ) {
			$reason = (string) ( $post->get_error_data()['reason'] ?? '' );
			return Publish_Result::failed( $post->get_error_message(), 'RateLimitExceeded' === $reason || 'InternalServerError' === $reason );
		}

		return Publish_Result::published( $post['uri'], self::post_url( $post['rkey'] ) );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		// publish() never returns processing.
		return Publish_Result::published( $reference, self::post_url( Bluesky_Client::rkey( $reference ) ) );
	}

	/**
	 * Text = description (or title) with tags as hashtags while they fit, cut to 300 graphemes.
	 *
	 * @param Media_Package $package Package.
	 */
	public static function text( Media_Package $package ): string {
		$text = trim( '' !== trim( $package->description() ) ? $package->description() : $package->title() );
		if ( self::length( $text ) > self::TEXT_MAX ) {
			return rtrim( self::cut( $text, self::TEXT_MAX - 1 ) ) . '…';
		}

		$hashtags = array();
		foreach ( $package->tags() as $tag ) {
			$tag = preg_replace( '/[^\p{L}\p{N}_]/u', '', $tag );
			if ( '' !== $tag && false === stripos( $text, '#' . $tag ) ) {
				$hashtags[] = '#' . $tag;
			}
		}

		$glue = "\n\n";
		foreach ( $hashtags as $hashtag ) {
			$next = $text . $glue . $hashtag;
			if ( self::length( $next ) > self::TEXT_MAX ) {
				break;
			}
			$text = $next;
			$glue = ' ';
		}

		return $text;
	}

	/**
	 * Public post URL (bsky.app resolves the DID).
	 *
	 * @param string $rkey Record key.
	 */
	private static function post_url( string $rkey ): string {
		$details = Bluesky_Account::details();
		$actor   = '' !== $details['handle'] ? $details['handle'] : $details['did'];

		return 'https://bsky.app/profile/' . rawurlencode( $actor ) . '/post/' . rawurlencode( $rkey );
	}

	/**
	 * Width / height from the file, so vertical videos are not shown as 16:9.
	 *
	 * @param string $path Video path.
	 * @return array{width: int, height: int}|null
	 */
	private static function aspect_ratio( string $path ): ?array {
		if ( ! function_exists( 'wp_read_video_metadata' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/media.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$meta = function_exists( 'wp_read_video_metadata' ) ? wp_read_video_metadata( $path ) : false;
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return null;
		}

		return array(
			'width'  => (int) $meta['width'],
			'height' => (int) $meta['height'],
		);
	}

	/**
	 * @param string $text Text.
	 */
	private static function length( string $text ): int {
		if ( function_exists( 'grapheme_strlen' ) ) {
			return (int) grapheme_strlen( $text );
		}

		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * @param string $text  Text.
	 * @param int    $limit Graphemes.
	 */
	private static function cut( string $text, int $limit ): string {
		if ( function_exists( 'grapheme_substr' ) ) {
			return (string) grapheme_substr( $text, 0, $limit );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}
}
