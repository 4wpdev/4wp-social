<?php
/**
 * Video post type, series taxonomy and native meta (REST + Block Bindings ready, no ACF).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Video;

use ForWP\Social\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Slugs come from Settings → Modules → Video, so a site can rename them or reuse an existing type.
 */
final class Video_Types {

	/**
	 * Meta key => [type, description]. Registered with show_in_rest for the editor and bindings.
	 */
	private const META = array(
		'episode_code'     => array( 'string', 'Episode label that ties the video to its material (W01, H01, C03).' ),
		'video_format'     => array( 'string', 'shorts (9:16) or landscape (16:9).' ),
		'duration'         => array( 'integer', 'Length in seconds.' ),
		'video_file_id'    => array( 'integer', 'Original video file (Media Library attachment).' ),
		'captions_file_id' => array( 'integer', 'Captions file, SRT or VTT (Media Library attachment).' ),
		'transcript'       => array( 'string', 'Plain-text transcript (filled from the captions file when empty).' ),
		'source_url'       => array( 'string', 'The material this video is about (post, page, component).' ),
		'youtube_id'       => array( 'string', 'YouTube video id (filled after publishing).' ),
	);

	public static function post_type(): string {
		return (string) Settings::get( 'video' )['post_type'];
	}

	public static function taxonomy(): string {
		return (string) Settings::get( 'video' )['taxonomy'];
	}

	public static function slug(): string {
		return (string) Settings::get( 'video' )['slug'];
	}

	/**
	 * Register everything (called from Video_Module::boot on init).
	 *
	 * @return void
	 */
	public static function register(): void {
		// Taxonomy first: its video/series/{term} rule must come before the CPT's video/{post}/{attachment} rule.
		self::register_taxonomy();
		self::register_post_type();
		// Shared site tags (not video-only), so videos and articles meet on one tag page.
		register_taxonomy_for_object_type( 'post_tag', self::post_type() );
		self::register_meta();
	}

	/**
	 * @return void
	 */
	private static function register_post_type(): void {
		$post_type = self::post_type();
		if ( post_type_exists( $post_type ) ) {
			// Reuse a type the site already has; only taxonomy and meta are added to it.
			add_post_type_support( $post_type, array( 'thumbnail', 'custom-fields', 'excerpt' ) );
			return;
		}

		register_post_type(
			$post_type,
			array(
				'labels'       => array(
					'name'               => __( 'Videos', '4wp-social' ),
					'singular_name'      => __( 'Video', '4wp-social' ),
					'menu_name'          => __( 'Videos', '4wp-social' ),
					'all_items'          => __( 'All Videos', '4wp-social' ),
					'add_new'            => __( 'Add Video', '4wp-social' ),
					'add_new_item'       => __( 'Add Video', '4wp-social' ),
					'edit_item'          => __( 'Edit Video', '4wp-social' ),
					'new_item'           => __( 'New Video', '4wp-social' ),
					'view_item'          => __( 'View Video', '4wp-social' ),
					'search_items'       => __( 'Search Videos', '4wp-social' ),
					'not_found'          => __( 'No videos found', '4wp-social' ),
					'not_found_in_trash' => __( 'No videos found in Trash', '4wp-social' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => self::slug(),
					'with_front' => false,
				),
				'menu_icon'    => 'dashicons-video-alt3',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'taxonomies'   => array( self::taxonomy() ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * @return void
	 */
	private static function register_taxonomy(): void {
		$taxonomy = self::taxonomy();
		if ( taxonomy_exists( $taxonomy ) ) {
			register_taxonomy_for_object_type( $taxonomy, self::post_type() );
			return;
		}

		register_taxonomy(
			$taxonomy,
			array( self::post_type() ),
			array(
				'labels'            => array(
					'name'          => __( 'Series', '4wp-social' ),
					'singular_name' => __( 'Series', '4wp-social' ),
					'search_items'  => __( 'Search Series', '4wp-social' ),
					'all_items'     => __( 'All Series', '4wp-social' ),
					'edit_item'     => __( 'Edit Series', '4wp-social' ),
					'update_item'   => __( 'Update Series', '4wp-social' ),
					'add_new_item'  => __( 'Add New Series', '4wp-social' ),
					'new_item_name' => __( 'New Series Name', '4wp-social' ),
					'menu_name'     => __( 'Series', '4wp-social' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => self::slug() . '/series',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * @return void
	 */
	private static function register_meta(): void {
		$post_type = self::post_type();

		foreach ( self::META as $key => $spec ) {
			list( $type, $description ) = $spec;

			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => $type,
					'description'       => $description,
					'single'            => true,
					'default'           => 'integer' === $type ? 0 : '',
					'show_in_rest'      => true,
					'sanitize_callback' => self::sanitizer( $key, $type ),
					'auth_callback'     => array( self::class, 'can_edit' ),
				)
			);
		}

		register_post_meta(
			$post_type,
			'platform_urls',
			array(
				'type'          => 'object',
				'description'   => 'Where the copies live: platform key => URL (filled after publishing).',
				'single'        => true,
				'default'       => array(),
				'show_in_rest'  => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
				'auth_callback' => array( self::class, 'can_edit' ),
			)
		);
	}

	/**
	 * @param string $key  Meta key.
	 * @param string $type Meta type.
	 * @return callable
	 */
	private static function sanitizer( string $key, string $type ): callable {
		if ( 'integer' === $type ) {
			return 'absint';
		}
		if ( 'source_url' === $key ) {
			return 'esc_url_raw';
		}
		if ( 'transcript' === $key ) {
			return 'sanitize_textarea_field';
		}
		if ( 'video_format' === $key ) {
			return static function ( $value ): string {
				$value = sanitize_key( (string) $value );

				return in_array( $value, array( 'shorts', 'landscape' ), true ) ? $value : '';
			};
		}

		return 'sanitize_text_field';
	}

	/**
	 * Meta is editable by whoever can edit the post.
	 *
	 * @param bool   $allowed  Unused.
	 * @param string $meta_key Unused.
	 * @param int    $post_id  Post id.
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', (int) $post_id );
	}
}
