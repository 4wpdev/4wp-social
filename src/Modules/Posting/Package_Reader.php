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
	 * A platform missing from meta.json reuses the package video of another platform,
	 * with the post's own title and excerpt + link as the copy.
	 *
	 * @param array<string, mixed> $snapshot Platforms snapshot (Package_Importer::snapshot()).
	 * @return array<string, mixed>|null Null when no platform has a video.
	 */
	public static function borrowed_item( array $snapshot ): ?array {
		foreach ( $snapshot as $item ) {
			if ( is_array( $item ) && ! empty( $item['video_id'] ) ) {
				return array_merge(
					array_intersect_key( $item, array_flip( array( 'video_id', 'captions_id', 'captions_name', 'cover_id' ) ) ),
					array(
						'entry' => array(),
						'copy'  => array(),
					)
				);
			}
		}

		return null;
	}

	/**
	 * Seconds to wait after publishing the original (meta.json republish.* wins over settings).
	 *
	 * @param array<string, mixed> $package  meta.json data.
	 * @param int|null             $fallback Seconds when meta.json has no delay (null = the API delay setting).
	 */
	public static function delay_seconds( array $package, ?int $fallback = null ): int {
		$republish = isset( $package['republish'] ) && is_array( $package['republish'] ) ? $package['republish'] : array();
		if ( isset( $republish['delay_hours'] ) || isset( $republish['delay_minutes'] ) ) {
			return max( 0, (int) ( $republish['delay_hours'] ?? 0 ) ) * HOUR_IN_SECONDS
				+ max( 0, (int) ( $republish['delay_minutes'] ?? 0 ) ) * MINUTE_IN_SECONDS;
		}

		return null !== $fallback ? max( 0, $fallback ) : max( 0, (int) Settings::get( 'delay_minutes', 0 ) ) * MINUTE_IN_SECONDS;
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
		if ( empty( $snapshot ) && $with_media ) {
			$imported = ( new Package_Importer() )->import( $post_id );
			if ( is_wp_error( $imported ) ) {
				return $imported;
			}
			$snapshot = Package_Importer::snapshot( $post_id );
		}
		$is_article = Adapter_Registry::is_article( $platform );
		$item       = is_array( $snapshot[ $platform ] ?? null ) ? $snapshot[ $platform ] : ( $is_article ? null : self::borrowed_item( $snapshot ) );
		if ( null === $item ) {
			return new WP_Error( 'forwp_social_not_declared', __( 'meta.json has no api entry for this platform.', '4wp-social' ) );
		}

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
			if ( ! $is_article && ( empty( $data['video_path'] ) || ! is_readable( $data['video_path'] ) ) ) {
				return new WP_Error( 'forwp_social_no_video', __( 'The platform video was not imported into the Media Library.', '4wp-social' ) );
			}
		}

		if ( $is_article ) {
			$data = self::with_article( $data, $item, $post_id, $excerpt );
			if ( is_wp_error( $data ) ) {
				return $data;
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
	 * Add the article from the package text file: its own fields win over the entry and the post.
	 *
	 * @param array<string, mixed> $data    Package fields.
	 * @param array<string, mixed> $item    Snapshot item.
	 * @param int                  $post_id Post id.
	 * @param string               $excerpt Post excerpt.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function with_article( array $data, array $item, int $post_id, string $excerpt ) {
		$permalink = (string) get_permalink( $post_id );
		$parsed    = Article_Parser::parse(
			(string) ( $item['article_text'] ?? '' ),
			array(
				'{{original.url}}'   => $permalink,
				'{{original.title}}' => get_the_title( $post_id ),
			)
		);
		if ( '' === $parsed['body'] ) {
			return new WP_Error( 'forwp_social_no_article', __( 'The article text file of this platform is missing or empty.', '4wp-social' ) );
		}

		$fields = $parsed['fields'];
		$entry  = $data['entry'];
		$cover  = (int) ( $item['cover_id'] ?? 0 );

		$data['title']       = self::first_filled( $parsed['title'], (string) $data['title'] );
		$data['description'] = self::first_filled( (string) ( $fields['description'] ?? '' ), (string) ( $entry['description'] ?? '' ), $excerpt );
		if ( ! empty( $fields['tags'] ) ) {
			$data['tags'] = array_map( 'trim', explode( ',', trim( $fields['tags'], '[] ' ) ) );
		}
		$data['article'] = array(
			'body'          => $parsed['body'],
			'canonical_url' => self::first_filled( (string) ( $fields['canonical_url'] ?? '' ), $permalink ),
			'main_image'    => self::first_filled( (string) ( $fields['main_image'] ?? '' ), (string) ( $fields['cover_image'] ?? '' ), $cover ? (string) wp_get_attachment_url( $cover ) : '' ),
			'series'        => self::first_filled( (string) ( $fields['series'] ?? '' ), (string) ( $entry['series'] ?? '' ) ),
			'draft'         => 'false' === strtolower( (string) ( $fields['published'] ?? '' ) ),
		);

		return $data;
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
