<?php
/**
 * Cross-posting REST: same routes and shapes as forwp-seo/v1/crosspost*, plus the calendar.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Core\Settings;
use ForWP\Social\Modules\Posting\Article_Parser;
use ForWP\Social\Modules\Posting\Calendar;
use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Manual_Flow;
use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Remote_Link;
use ForWP\Social\Modules\Posting\Status_Column;
use ForWP\Social\Modules\Posting\Status_Index;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * /forwp-social/v1/crosspost*, /forwp-social/v1/calendar
 */
final class Rest_Crosspost {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * @return void
	 */
	public static function routes(): void {
		$post_arg     = array(
			'type'     => 'integer',
			'required' => true,
		);
		$platform_arg = array(
			'type'     => 'string',
			'required' => true,
		);

		$routes = array(
			'/crosspost'           => array(
				'GET',
				'status',
				'can_access',
				array(
					'post_id' => $post_arg,
					'fresh'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			'/crosspost/payload'   => array(
				'GET',
				'payload',
				'can_access',
				array(
					'post_id'  => $post_arg,
					'platform' => $platform_arg,
				),
			),
			'/crosspost/published' => array(
				'POST',
				'published',
				'can_access',
				array(
					'post_id'  => $post_arg,
					'platform' => $platform_arg,
					'url'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'via'      => array(
						'type'    => 'string',
						'enum'    => array( 'extension', 'manual' ),
						'default' => 'extension',
					),
				),
			),
			'/crosspost/notify'    => array(
				'POST',
				'notify',
				'can_access',
				array( 'post_id' => $post_arg ),
			),
			'/crosspost/board'     => array(
				'GET',
				'board',
				'can_list',
				array(
					'platform' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
			'/calendar'            => array(
				'GET',
				'calendar',
				'can_calendar',
				array(
					'month' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			),
		);

		foreach ( $routes as $path => $route ) {
			register_rest_route(
				Rest_Oauth::NAMESPACE_V1,
				$path,
				array(
					'methods'             => $route[0],
					'callback'            => array( self::class, $route[1] ),
					'permission_callback' => array( self::class, $route[2] ),
					'args'                => $route[3],
				)
			);
		}
	}

	/**
	 * @return bool
	 */
	public static function can_list(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * The calendar names every post, drafts of other authors included.
	 */
	public static function can_calendar(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public static function can_access( WP_REST_Request $request ): bool {
		$post_id = (int) $request['post_id'];

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Package platforms enabled on this site, with their status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function status( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$package = ( new Package_Reader() )->package( $post_id, (bool) $request['fresh'] );
		if ( is_wp_error( $package ) ) {
			return new WP_Error( 'forwp_social_package', $package->get_error_message(), array( 'status' => 502 ) );
		}
		if ( ! is_array( $package ) ) {
			return array(
				'package'   => false,
				'platforms' => array(),
			);
		}

		// The package was read anyway: keep the index in step with it.
		Status_Index::sync_package( $post_id );

		$republish   = isset( $package['republish'] ) && is_array( $package['republish'] ) ? $package['republish'] : array();
		$delay_hours = isset( $republish['delay_hours'] ) ? max( 0, (int) $republish['delay_hours'] ) : (int) floor( (int) Settings::get( 'manual_delay_minutes', 1440 ) / 60 );
		$files       = apply_filters( 'forwp_drive_package_files', null, $post_id );
		$files       = is_array( $files ) ? $files : null;
		$declared    = isset( $package['platforms'] ) && is_array( $package['platforms'] ) ? $package['platforms'] : array();
		$original    = isset( $package['original'] ) && is_array( $package['original'] ) ? $package['original'] : array();

		$platforms = array();
		foreach ( Platform_Registry::all() as $key => $platform ) {
			if ( ! isset( $declared[ $key ] ) || ! is_array( $declared[ $key ] ) ) {
				continue;
			}
			$entry  = $declared[ $key ];
			$status = (string) ( $entry['status'] ?? 'open' );
			$url    = (string) ( $entry['url'] ?? '' );
			$file   = (string) ( $entry['file'] ?? '' );

			$platforms[] = array(
				'key'       => $key,
				'label'     => $platform['label'],
				'mode'      => Status_Index::mode( $entry, $platform ),
				'format'    => (string) ( $entry['format'] ?? $platform['format'] ),
				'status'    => $status,
				'posted'    => 'posted' === $status,
				'variant'   => self::variant_state( $status, $file, $files ),
				'url'       => $url,
				'open_url'  => '' !== $url ? $url : $platform['url'],
				'posted_at' => (string) ( $entry['posted_at'] ?? '' ),
				'file'      => $file,
			);
		}

		$published_ts = ! empty( $original['published_at'] ) ? strtotime( (string) $original['published_at'] ) : false;

		return array(
			'package'      => true,
			'delay_hours'  => $delay_hours,
			'republish_at' => $published_ts ? gmdate( 'c', $published_ts + $delay_hours * HOUR_IN_SECONDS ) : '',
			'original'     => array(
				'status'       => (string) ( $original['status'] ?? '' ),
				'url'          => (string) ( $original['url'] ?? '' ),
				'published_at' => (string) ( $original['published_at'] ?? '' ),
			),
			'sync_error'   => (string) get_post_meta( $post_id, '_forwp_drive_package_error', true ),
			'platforms'    => $platforms,
		);
	}

	/**
	 * Everything the Chrome extension needs to post one platform variant.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function payload( WP_REST_Request $request ) {
		$post_id  = (int) $request['post_id'];
		$key      = strtolower( sanitize_text_field( (string) $request['platform'] ) );
		$platform = Platform_Registry::all()[ $key ] ?? null;
		if ( null === $platform ) {
			return new WP_Error( 'forwp_social_platform', __( 'This platform is not enabled.', '4wp-social' ), array( 'status' => 404 ) );
		}

		$package = ( new Package_Reader() )->package( $post_id );
		if ( is_wp_error( $package ) ) {
			return new WP_Error( 'forwp_social_package', $package->get_error_message(), array( 'status' => 502 ) );
		}
		if ( ! is_array( $package ) || empty( $package['platforms'][ $key ] ) || ! is_array( $package['platforms'][ $key ] ) ) {
			return new WP_Error( 'forwp_social_not_declared', __( 'This post has no variant for this platform.', '4wp-social' ), array( 'status' => 404 ) );
		}

		$entry = $package['platforms'][ $key ];
		// The mark lives in the index: it stays visible even if writing to meta.json failed.
		$known = Status_Index::get( $post_id )[ $key ] ?? array();
		if ( Job_Repository::PUBLISHED === ( $known['status'] ?? '' ) ) {
			$entry = array_merge(
				$entry,
				array(
					'status' => 'posted',
					'url'    => (string) ( $known['url'] ?? '' ),
					'slug'   => (string) ( $known['remote_id'] ?? '' ),
				)
			);
		}

		$file     = (string) ( $entry['file'] ?? '' );
		$status   = (string) ( $entry['status'] ?? 'open' );
		$files    = apply_filters( 'forwp_drive_package_files', null, $post_id );
		$original = isset( $package['original'] ) && is_array( $package['original'] ) ? $package['original'] : array();
		$text     = '';
		$error    = '';

		if ( '' !== $file ) {
			$read = apply_filters( 'forwp_drive_package_file', null, $post_id, $file );
			if ( is_wp_error( $read ) ) {
				$error = $read->get_error_message();
			} elseif ( is_string( $read ) ) {
				$text = $read;
			}
		}

		$parsed = Article_Parser::parse(
			$text,
			array(
				'{{original.url}}'   => (string) ( $original['url'] ?? '' ),
				'{{original.title}}' => get_the_title( $post_id ),
			)
		);

		return array(
			'post_id'  => $post_id,
			'title'    => get_the_title( $post_id ),
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'original' => array(
				'status' => (string) ( $original['status'] ?? '' ),
				'url'    => (string) ( $original['url'] ?? '' ),
			),
			'platform' => array(
				'key'      => $key,
				'label'    => $platform['label'],
				'mode'     => Status_Index::mode( $entry, $platform ),
				'format'   => (string) ( $entry['format'] ?? $platform['format'] ),
				'status'   => $status,
				'rules'    => isset( $entry['rules'] ) && is_array( $entry['rules'] ) ? array_values( $entry['rules'] ) : array(),
				'media'    => isset( $entry['media'] ) && is_array( $entry['media'] ) ? array_values( $entry['media'] ) : array(),
				'url'      => (string) ( $entry['url'] ?? '' ),
				'slug'     => (string) ( $entry['slug'] ?? '' ),
				'open_url' => $platform['url'],
			),
			'variant'  => array(
				'file'    => $file,
				'state'   => self::variant_state( $status, $file, is_array( $files ) ? $files : null ),
				'title'   => $parsed['title'],
				'fields'  => (object) $parsed['fields'],
				'content' => $parsed['body'],
				'error'   => $error,
			),
			'image'    => self::post_image( $post_id, $package ),
		);
	}

	/**
	 * Record a publication made by a person or the extension.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, string>|WP_Error
	 */
	public static function published( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$key     = strtolower( sanitize_text_field( (string) $request['platform'] ) );
		$url     = esc_url_raw( (string) $request['url'] );

		if ( ! isset( Platform_Registry::all()[ $key ] ) ) {
			return new WP_Error( 'forwp_social_platform', __( 'This platform is not enabled.', '4wp-social' ), array( 'status' => 404 ) );
		}
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'forwp_social_url', __( 'Open the published page first.', '4wp-social' ), array( 'status' => 400 ) );
		}

		$slug = self::slug_from_url( $url );
		if ( '' === $slug ) {
			return new WP_Error( 'forwp_social_slug', __( 'This page has no slug.', '4wp-social' ), array( 'status' => 400 ) );
		}

		$row = Remote_Link::link( $post_id, $key, $slug, $url, (string) $request['via'] );

		$synced = apply_filters(
			'forwp_drive_update_package',
			null,
			$post_id,
			array(
				'platforms' => array(
					$key => array(
						'status'    => 'posted',
						'url'       => $url,
						'slug'      => $slug,
						'posted_at' => $row['at'],
					),
				),
			)
		);

		return array(
			'status'     => 'posted',
			'url'        => $url,
			'slug'       => $slug,
			'posted_at'  => $row['at'],
			'sync_error' => is_wp_error( $synced ) ? $synced->get_error_message() : '',
		);
	}

	/**
	 * Send the Telegram list now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, bool>|WP_Error
	 */
	public static function notify( WP_REST_Request $request ) {
		$result = Manual_Flow::notify( (int) $request['post_id'] );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 ) < 400 ) {
				$result->add_data( array( 'status' => 502 ) );
			}
			return $result;
		}

		return array( 'sent' => true );
	}

	/**
	 * Enabled platforms and the posts still waiting on one of them. Reads the local index only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public static function board( WP_REST_Request $request ): array {
		$platforms = array();
		foreach ( Platform_Registry::all() as $row ) {
			$platforms[] = array(
				'key'   => $row['key'],
				'label' => $row['label'],
				'url'   => $row['url'],
			);
		}

		$key = strtolower( sanitize_text_field( (string) $request['platform'] ) );
		if ( ! in_array( $key, wp_list_pluck( $platforms, 'key' ), true ) ) {
			$key = '';
		}

		return array(
			'platforms' => $platforms,
			'platform'  => $key,
			'materials' => '' === $key ? array() : self::waiting_materials( $key ),
		);
	}

	/**
	 * Published posts of this platform that a person can still post. Queue work is not offered.
	 *
	 * @param string $key Platform key.
	 * @return array<int, array{post_id: int, title: string, status: string}>
	 */
	private static function waiting_materials( string $key ): array {
		$ids = get_posts(
			array(
				'post_type'      => Status_Column::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_key'       => Status_Index::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only posts with statuses.
			)
		);

		$materials = array();
		foreach ( $ids as $post_id ) {
			$status = (string) ( Status_Index::get( (int) $post_id )[ $key ]['status'] ?? '' );
			if ( in_array( $status, array( Status_Index::PLANNED, Status_Index::MANUAL_PENDING, Job_Repository::FAILED ), true ) ) {
				$materials[] = array(
					'post_id' => (int) $post_id,
					'title'   => get_the_title( (int) $post_id ),
					'status'  => $status,
				);
			}
		}

		return $materials;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public static function calendar( WP_REST_Request $request ): array {
		$month = Calendar::month( (string) $request['month'] );

		return array(
			'month'  => $month,
			'events' => Calendar::events( $month ),
			'site'   => Calendar::site_events( $month ),
		);
	}

	/**
	 * Last path segment of a page. Pure.
	 *
	 * @param string $url URL.
	 */
	public static function slug_from_url( string $url ): string {
		$path  = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		$slug  = trim( rawurldecode( (string) end( $parts ) ) );
		if ( '' === $slug || false !== strpos( $slug, '..' ) || strlen( $slug ) > 200 ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Readiness of the platform text: none, missing, empty, draft, skipped or ready. Pure.
	 *
	 * @param string                  $status meta.json status.
	 * @param string                  $file   Variant file name.
	 * @param array<string, int>|null $files  Package files, null when unknown.
	 */
	public static function variant_state( string $status, string $file, ?array $files ): string {
		if ( '' === $file ) {
			return 'none';
		}
		if ( null !== $files ) {
			if ( ! array_key_exists( $file, $files ) ) {
				return 'missing';
			}
			if ( 0 === $files[ $file ] ) {
				return 'empty';
			}
		}
		if ( 'skipped' === $status ) {
			return 'skipped';
		}

		return in_array( $status, array( 'ready', 'scheduled', 'posted' ), true ) ? 'ready' : 'draft';
	}

	/**
	 * Featured image, or the package cover when the post has none.
	 *
	 * @param int                  $post_id Post id.
	 * @param array<string, mixed> $package meta.json data.
	 * @return array{name: string, url: string, file: string}|null
	 */
	private static function post_image( int $post_id, array $package ): ?array {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		$url      = $thumb_id > 0 ? (string) wp_get_attachment_url( $thumb_id ) : '';
		if ( '' !== $url ) {
			$path = (string) get_attached_file( $thumb_id );

			return array(
				'name' => '' !== $path ? basename( $path ) : 'cover.jpg',
				'url'  => $url,
				'file' => '',
			);
		}

		foreach ( isset( $package['media'] ) && is_array( $package['media'] ) ? $package['media'] : array() as $item ) {
			$file = is_array( $item ) ? str_replace( '\\', '/', trim( (string) ( $item['file'] ?? '' ) ) ) : '';
			if ( '' !== $file && false === strpos( $file, '..' ) && preg_match( '/\.(png|jpe?g|gif|webp)$/i', $file ) ) {
				return array(
					'name' => basename( $file ),
					'url'  => '',
					'file' => $file,
				);
			}
		}

		return null;
	}
}
