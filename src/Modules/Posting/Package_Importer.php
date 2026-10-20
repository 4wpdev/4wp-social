<?php
/**
 * The "fat" path: during a 4WP Drive import, copy what meta.json declares for API platforms into WordPress.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Video / captions / cover → Media Library (attached to the post); platform copy file → post meta.
 * After this, publishing needs nothing from Drive or GitHub.
 */
final class Package_Importer {

	/**
	 * Snapshot: {platform: {entry, video_id, captions_id, cover_id, captions_name, copy}}.
	 */
	public const PLATFORMS_META = '_forwp_social_platforms';

	/**
	 * Package file name => {id, size}: reuse attachments on re-import.
	 */
	public const MEDIA_META = '_forwp_social_package_media';

	/**
	 * Last import: {at, hash, errors}.
	 */
	public const IMPORT_META = '_forwp_social_import';

	private const MEDIA_KEYS = array( 'video', 'captions', 'cover' );

	/**
	 * Drive hook: forwp_drive_document_imported( $post_id, $document_id ).
	 *
	 * @param int $post_id     Post id.
	 * @param int $document_id Drive document row (unused).
	 * @return void
	 */
	public static function on_imported( $post_id, $document_id = 0 ): void {
		unset( $document_id );
		$post_id = (int) $post_id;
		if ( ( new Package_Reader() )->is_package_post( $post_id ) ) {
			( new self() )->import( $post_id );
		}
	}

	/**
	 * Saved snapshot of a post.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function snapshot( int $post_id ): array {
		$data = get_post_meta( $post_id, self::PLATFORMS_META, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Hash of what the import depends on (media names + sizes, copy file), not of statuses.
	 *
	 * @param array<string, mixed>   $package meta.json data.
	 * @param array<string, int>|null $files   Package file sizes.
	 */
	public static function hash( array $package, ?array $files ): string {
		$parts = array();
		foreach ( Package_Reader::api_platforms( $package ) as $platform ) {
			$entry = $package['platforms'][ $platform ];
			foreach ( array_merge( self::MEDIA_KEYS, array( 'file' ) ) as $key ) {
				$name                          = basename( (string) ( $entry[ $key ] ?? '' ) );
				$parts[ $platform . ':' . $key ] = $name . '#' . ( $files[ $name ] ?? 0 );
			}
		}

		return md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * Whether the snapshot matches the current meta.json.
	 *
	 * @param int                  $post_id Post id.
	 * @param array<string, mixed> $package meta.json data.
	 */
	public function is_current( int $post_id, array $package ): bool {
		$last  = get_post_meta( $post_id, self::IMPORT_META, true );
		$files = apply_filters( 'forwp_drive_package_files', null, $post_id );

		return is_array( $last ) && ( $last['hash'] ?? '' ) === self::hash( $package, is_array( $files ) ? $files : null );
	}

	/**
	 * Import media and copy for every api platform of meta.json.
	 *
	 * @param int $post_id Post id.
	 * @return array{platforms: array<int, string>, errors: array<int, string>}|WP_Error
	 */
	public function import( int $post_id ) {
		$package = ( new Package_Reader() )->package( $post_id, true );
		if ( is_wp_error( $package ) ) {
			return $package;
		}
		if ( ! is_array( $package ) ) {
			return new WP_Error( 'forwp_social_no_package', __( 'This post has no meta.json package (or 4WP Drive is inactive).', '4wp-social' ) );
		}

		$files = apply_filters( 'forwp_drive_package_files', null, $post_id );
		$files = is_array( $files ) ? $files : null;

		$media    = get_post_meta( $post_id, self::MEDIA_META, true );
		$media    = is_array( $media ) ? $media : array();
		$snapshot = array();
		$errors   = array();

		foreach ( Package_Reader::api_platforms( $package ) as $platform ) {
			$entry = $package['platforms'][ $platform ];
			$item  = array(
				'entry' => $entry,
				'copy'  => $this->read_copy( $post_id, (string) ( $entry['file'] ?? '' ) ),
			);

			foreach ( self::MEDIA_KEYS as $key ) {
				$name = basename( str_replace( '\\', '/', (string) ( $entry[ $key ] ?? '' ) ) );
				if ( '' === $name ) {
					continue;
				}

				$id = $this->ensure_attachment( $post_id, $name, $files, $media );
				if ( is_wp_error( $id ) ) {
					$errors[] = $platform . ' ' . $key . ': ' . $id->get_error_message();
					continue;
				}

				$item[ $key . '_id' ] = $id;
				if ( 'captions' === $key ) {
					$item['captions_name'] = $name;
				}
			}

			$snapshot[ $platform ] = $item;
		}

		update_post_meta( $post_id, self::MEDIA_META, $media );
		update_post_meta( $post_id, self::PLATFORMS_META, $snapshot );
		update_post_meta(
			$post_id,
			self::IMPORT_META,
			array(
				'at'     => gmdate( 'c' ),
				'hash'   => self::hash( $package, $files ),
				'errors' => $errors,
			)
		);

		/**
		 * Fires after Social copied package media and texts into WordPress.
		 *
		 * @param int                  $post_id  Post id.
		 * @param array<string, mixed> $snapshot Platforms snapshot.
		 * @param array<int, string>   $errors   Problems per file.
		 */
		do_action( 'forwp_social_package_imported', $post_id, $snapshot, $errors );

		return array(
			'platforms' => array_keys( $snapshot ),
			'errors'    => $errors,
		);
	}

	/**
	 * Parsed platform copy file.
	 *
	 * @param int    $post_id Post id.
	 * @param string $name    File name ('' = none).
	 * @return array{title: string, description: string, caption: string, tags: array<int, string>}
	 */
	private function read_copy( int $post_id, string $name ): array {
		$name = basename( str_replace( '\\', '/', $name ) );
		$text = '' !== $name ? apply_filters( 'forwp_drive_package_file', null, $post_id, $name ) : null;

		return Platform_Copy_Parser::parse( is_string( $text ) ? $text : '' );
	}

	/**
	 * Existing attachment for the same file and size, or a new one.
	 *
	 * @param int                                       $post_id Post id.
	 * @param string                                    $name    File name in the package.
	 * @param array<string, int>|null                   $files   Package file sizes.
	 * @param array<string, array{id: int, size: int}> $media   Name map (by reference).
	 * @return int|WP_Error
	 */
	private function ensure_attachment( int $post_id, string $name, ?array $files, array &$media ) {
		$size  = isset( $files[ $name ] ) ? (int) $files[ $name ] : -1;
		$known = $media[ $name ] ?? null;
		if ( is_array( $known ) && get_post( (int) $known['id'] ) && ( -1 === $size || (int) $known['size'] === $size ) ) {
			return (int) $known['id'];
		}

		/**
		 * Return a local path for a package file (S3, mounted storage) to skip the Drive download.
		 *
		 * @param string|null $path    Local path.
		 * @param int         $post_id Post id.
		 * @param string      $name    File name from meta.json.
		 */
		$local = apply_filters( 'forwp_social_package_media_path', null, $post_id, $name );
		if ( is_string( $local ) && '' !== $local && is_readable( $local ) ) {
			$bytes = file_get_contents( $local ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		} else {
			$bytes = apply_filters( 'forwp_drive_package_file', null, $post_id, $name );
		}

		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			/* translators: %s: file name. */
			return new WP_Error( 'forwp_social_missing_file', sprintf( __( 'Package file "%s" was not found (only files in the package folder root are supported).', '4wp-social' ), $name ) );
		}

		$id = self::sideload( $bytes, $name, $post_id );
		if ( ! is_wp_error( $id ) ) {
			$media[ $name ] = array(
				'id'   => $id,
				'size' => strlen( $bytes ),
			);
		}

		return $id;
	}

	/**
	 * Write bytes to uploads and create an attachment of the post.
	 *
	 * @param string $bytes   File contents.
	 * @param string $name    File name.
	 * @param int    $post_id Parent post.
	 * @return int|WP_Error
	 */
	public static function sideload( string $bytes, string $name, int $post_id ) {
		$upload = wp_upload_bits( sanitize_file_name( $name ), null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'forwp_social_upload', (string) $upload['error'] );
		}

		$type = wp_check_filetype( $upload['file'], null );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream',
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $name ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_read_video_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$meta = wp_generate_attachment_metadata( (int) $id, $upload['file'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( (int) $id, $meta );
		}

		return (int) $id;
	}
}
