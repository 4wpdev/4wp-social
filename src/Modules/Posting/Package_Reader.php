<?php
/**
 * Build a Media_Package for a package post (meta.json imported into WordPress).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Contracts\Media_Source_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * meta.json is read only through 4WP Drive filters (Drive is the only writer); media come from the Media Library.
 */
final class Package_Reader implements Media_Source_Interface {

	/**
	 * Post meta written by 4WP Drive on package import (ForWP\Drive\Import\Import_Runner::PACKAGE_POST_META).
	 */
	public const DRIVE_PACKAGE_META = '_forwp_drive_package';

	/**
	 * meta.json data, null when the post has no package or Drive is inactive.
	 *
	 * @param int  $post_id Post id.
	 * @param bool $fresh   Skip Drive's cache.
	 * @return array<string, mixed>|WP_Error|null
	 */
	public function package( int $post_id, bool $fresh = false ) {
		$package = apply_filters( 'forwp_drive_package', null, $post_id, $fresh );

		return ( is_array( $package ) || is_wp_error( $package ) ) ? $package : null;
	}

	/**
	 * Whether 4WP Drive imported this post from a package folder.
	 *
	 * @param int $post_id Post id.
	 */
	public function is_package_post( int $post_id ): bool {
		return ! empty( get_post_meta( $post_id, self::DRIVE_PACKAGE_META, true ) );
	}

	/**
	 * Platform keys with mode "api".
	 *
	 * @param array<string, mixed> $package meta.json data.
	 * @return array<int, string>
	 */
	public static function api_platforms( array $package ): array {
		$keys = array();
		foreach ( (array) ( $package['platforms'] ?? array() ) as $key => $entry ) {
			if ( is_array( $entry ) && 'api' === ( $entry['mode'] ?? '' ) ) {
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}

	/**
	 * Seconds to wait after publishing the original (meta.json republish.* wins over settings).
	 *
	 * @param array<string, mixed> $package meta.json data.
	 */
	public static function delay_seconds( array $package ): int {
		$republish = isset( $package['republish'] ) && is_array( $package['republish'] ) ? $package['republish'] : array();
		if ( isset( $republish['delay_hours'] ) || isset( $republish['delay_minutes'] ) ) {
			return max( 0, (int) ( $republish['delay_hours'] ?? 0 ) ) * HOUR_IN_SECONDS
				+ max( 0, (int) ( $republish['delay_minutes'] ?? 0 ) ) * MINUTE_IN_SECONDS;
		}

		return max( 0, (int) Settings::get( 'delay_minutes', 0 ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Build the package for one platform from the WordPress snapshot made by Package_Importer.
	 * Imports on demand when the snapshot is missing (post imported before Social was active).
	 *
	 * @param int                  $post_id    Post id.
	 * @param string               $platform   Platform key.
	 * @param bool                 $with_media Resolve attachment paths.
	 * @param array<string, mixed> $options    Job options (privacy override).
	 * @return Media_Package|WP_Error
	 */
	public function build( int $post_id, string $platform, bool $with_media, array $options = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'forwp_social_no_post', __( 'Post not found.', '4wp-social' ) );
		}

		$snapshot = Package_Importer::snapshot( $post_id );
		if ( ! isset( $snapshot[ $platform ] ) && $with_media ) {
			$imported = ( new Package_Importer() )->import( $post_id );
			if ( is_wp_error( $imported ) ) {
				return $imported;
			}
			$snapshot = Package_Importer::snapshot( $post_id );
		}
		if ( ! isset( $snapshot[ $platform ] ) || ! is_array( $snapshot[ $platform ] ) ) {
			return new WP_Error( 'forwp_social_not_declared', __( 'meta.json has no api entry for this platform.', '4wp-social' ) );
		}

		$item  = $snapshot[ $platform ];
		$entry = is_array( $item['entry'] ?? null ) ? $item['entry'] : array();
		$copy  = array_merge( Platform_Copy_Parser::parse( '' ), is_array( $item['copy'] ?? null ) ? $item['copy'] : array() );

		$excerpt  = wp_strip_all_tags( (string) get_the_excerpt( $post ) );
		$fallback = trim( $excerpt . "\n\n" . get_permalink( $post ) );

		$data = array(
			'post_id'           => $post_id,
			'platform'          => $platform,
			'entry'             => $entry,
			'title'             => self::first_filled( (string) $copy['title'], (string) ( $entry['title'] ?? '' ), get_the_title( $post ) ),
			'description'       => self::first_filled( (string) $copy['description'], (string) $copy['caption'], (string) ( $entry['description'] ?? '' ), $fallback ),
			'tags'              => ! empty( $copy['tags'] ) ? (array) $copy['tags'] : (array) ( $entry['tags'] ?? array() ),
			'privacy'           => self::first_filled( (string) ( $options['privacy'] ?? '' ), (string) ( $entry['privacy'] ?? '' ), (string) Settings::get( 'default_privacy', 'public' ) ),
			'captions_language' => Media_Package::language_from_file( (string) ( $item['captions_name'] ?? '' ), (string) Settings::get( 'caption_language', 'en' ) ),
			'cover_ms'          => (int) ( $entry['cover_ms'] ?? 0 ),
		);

		if ( $with_media ) {
			foreach ( array( 'video', 'captions', 'cover' ) as $key ) {
				$id = (int) ( $item[ $key . '_id' ] ?? 0 );
				if ( $id ) {
					$data[ $key . '_path' ] = (string) get_attached_file( $id );
				}
			}
			if ( empty( $data['video_path'] ) || ! is_readable( $data['video_path'] ) ) {
				return new WP_Error( 'forwp_social_no_video', __( 'The platform video was not imported into the Media Library.', '4wp-social' ) );
			}
		}

		$media = new Media_Package( $data );

		/**
		 * Filter the package before it goes to the adapter.
		 *
		 * @param Media_Package $media    Package.
		 * @param int           $post_id  Post id.
		 * @param string        $platform Platform key.
		 */
		$filtered = apply_filters( 'forwp_social_media_package', $media, $post_id, $platform );

		return $filtered instanceof Media_Package ? $filtered : $media;
	}

	/**
	 * First non-empty string.
	 *
	 * @param string ...$values Candidates.
	 */
	private static function first_filled( string ...$values ): string {
		foreach ( $values as $value ) {
			if ( '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}
}
