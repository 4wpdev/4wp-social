<?php
/**
 * YouTube: video → thumbnail → captions → playlist.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Api\YouTube_Client;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes synchronously: YouTube returns the video id when the upload finishes.
 */
final class YouTube_Adapter implements Platform_Adapter_Interface {

	public const SLUG = 'youtube.com';

	/**
	 * Science & Technology.
	 */
	private const DEFAULT_CATEGORY = '28';

	/**
	 * Errors a retry in a few minutes will not fix.
	 */
	private const FATAL_REASONS = array( 'quotaExceeded', 'uploadLimitExceeded', 'forbidden', 'insufficientPermissions', 'youtubeSignupRequired' );

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'YouTube';
	}

	public function is_ready(): bool {
		return Credentials_Store::has( self::SLUG ) && OAuth_Controller::instance()->is_connected( self::SLUG );
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $package->has_video() ) {
			return Publish_Result::failed( __( 'No video file to upload.', '4wp-social' ), false );
		}

		$token = OAuth_Controller::instance()->get_access_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			return Publish_Result::failed( $token->get_error_message(), false );
		}

		$client = new YouTube_Client( $token );
		$video  = $client->upload_video( $package->video_path(), self::build_resource( $package ) );
		if ( is_wp_error( $video ) ) {
			$reason = (string) ( $video->get_error_data()['reason'] ?? '' );
			return Publish_Result::failed( $video->get_error_message(), ! in_array( $reason, self::FATAL_REASONS, true ) );
		}

		$id       = (string) $video['id'];
		$warnings = array();

		if ( '' !== $package->cover_path() ) {
			$thumb = $client->set_thumbnail( $id, $package->cover_path() );
			if ( is_wp_error( $thumb ) ) {
				/* translators: %s: error. */
				$warnings[] = sprintf( __( 'Thumbnail: %s', '4wp-social' ), $thumb->get_error_message() );
			}
		}

		if ( '' !== $package->captions_path() ) {
			$caption = $client->insert_caption( $id, $package->captions_path(), $package->captions_language() );
			if ( is_wp_error( $caption ) ) {
				/* translators: %s: error. */
				$warnings[] = sprintf( __( 'Captions: %s', '4wp-social' ), $caption->get_error_message() );
			}
		}

		$playlist = trim( (string) ( $package->entry()['playlist'] ?? '' ) );
		if ( '' !== $playlist ) {
			$added = $client->add_to_playlist( $id, $playlist );
			if ( is_wp_error( $added ) ) {
				/* translators: %s: error. */
				$warnings[] = sprintf( __( 'Playlist: %s', '4wp-social' ), $added->get_error_message() );
			}
		}

		return Publish_Result::published( $id, self::video_url( $id, $package ), implode( ' ', $warnings ) );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		return Publish_Result::failed( __( 'YouTube publishes synchronously; nothing to poll.', '4wp-social' ), false );
	}

	/**
	 * videos.insert resource from the package.
	 *
	 * @param Media_Package $package Package.
	 * @return array<string, mixed>
	 */
	public static function build_resource( Media_Package $package ): array {
		$entry   = $package->entry();
		$privacy = in_array( $package->privacy(), array( 'public', 'unlisted', 'private' ), true ) ? $package->privacy() : 'public';
		// Video and title/description language: without it Studio greys out the uploaded captions.
		$lang = sanitize_text_field( (string) ( $entry['language'] ?? $package->captions_language() ) );

		$snippet = array(
			'title'       => self::clean_title( $package->title() ),
			'description' => self::clean_description( $package->description() ),
			'tags'        => self::limit_tags( $package->tags() ),
			'categoryId'  => (string) ( $entry['category_id'] ?? self::DEFAULT_CATEGORY ),
		);
		if ( '' !== $lang ) {
			$snippet['defaultLanguage']      = $lang;
			$snippet['defaultAudioLanguage'] = $lang;
		}

		return array(
			'snippet' => $snippet,
			'status'  => array(
				'privacyStatus'           => $privacy,
				'selfDeclaredMadeForKids' => ! empty( $entry['made_for_kids'] ),
			),
		);
	}

	/**
	 * Title: no angle brackets, at most 100 characters.
	 *
	 * @param string $title Title.
	 */
	public static function clean_title( string $title ): string {
		$title = trim( str_replace( array( '<', '>' ), '', $title ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 100 ) : substr( $title, 0, 100 );
	}

	/**
	 * Description: no angle brackets, at most 5000 bytes.
	 *
	 * @param string $description Description.
	 */
	public static function clean_description( string $description ): string {
		$description = str_replace( array( '<', '>' ), '', $description );
		if ( strlen( $description ) <= 5000 ) {
			return $description;
		}

		return function_exists( 'mb_strcut' ) ? mb_strcut( $description, 0, 5000 ) : substr( $description, 0, 5000 );
	}

	/**
	 * Tags: total length (with separators) at most 500 characters.
	 *
	 * @param array<int, string> $tags Tags.
	 * @return array<int, string>
	 */
	public static function limit_tags( array $tags ): array {
		$out   = array();
		$total = 0;
		foreach ( $tags as $tag ) {
			$tag = trim( str_replace( array( '<', '>', ',' ), '', $tag ) );
			if ( '' === $tag ) {
				continue;
			}
			$cost = strlen( $tag ) + ( false !== strpos( $tag, ' ' ) ? 2 : 0 ) + ( $out ? 1 : 0 );
			if ( $total + $cost > 500 ) {
				break;
			}
			$out[]  = $tag;
			$total += $cost;
		}

		return $out;
	}

	/**
	 * Public URL; Shorts URL when the entry type is "short".
	 *
	 * @param string        $id      Video id.
	 * @param Media_Package $package Package.
	 */
	private static function video_url( string $id, Media_Package $package ): string {
		$type = (string) ( $package->entry()['type'] ?? '' );

		return 'short' === $type
			? 'https://www.youtube.com/shorts/' . rawurlencode( $id )
			: 'https://www.youtube.com/watch?v=' . rawurlencode( $id );
	}
}
