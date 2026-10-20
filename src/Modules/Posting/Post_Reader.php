<?php
/**
 * Build a Media_Package from the post itself (button in the editor, MCP), no meta.json needed.
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
 * Media from the Media Library: video / captions / cover attachment ids saved on the post or passed with the job.
 */
final class Post_Reader implements Media_Source_Interface {

	/**
	 * Post meta with the editor box choices: video_id, captions_id, cover_id, privacy, title, description.
	 */
	public const META = '_forwp_social_post_media';

	/**
	 * Saved editor choices.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, mixed>
	 */
	public static function saved( int $post_id ): array {
		$data = get_post_meta( $post_id, self::META, true );

		/**
		 * Filter the saved editor choices (a post type can supply its own video / captions / cover).
		 *
		 * @param array<string, mixed> $data    Saved choices.
		 * @param int                  $post_id Post id.
		 */
		$data = apply_filters( 'forwp_social_post_media', is_array( $data ) ? $data : array(), $post_id );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Sanitize editor / REST / ability input.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$privacy = sanitize_key( (string) ( $input['privacy'] ?? '' ) );

		return array(
			'video_id'    => absint( $input['video_id'] ?? 0 ),
			'captions_id' => absint( $input['captions_id'] ?? 0 ),
			'cover_id'    => absint( $input['cover_id'] ?? 0 ),
			'cover_ms'    => absint( $input['cover_ms'] ?? 0 ),
			'privacy'     => in_array( $privacy, array( 'public', 'unlisted', 'private', 'followers', 'friends' ), true ) ? $privacy : '',
			'title'       => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
			'description' => sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ),
		);
	}

	/**
	 * Job options win over saved editor choices.
	 *
	 * @param int                  $post_id    Post id.
	 * @param string               $platform   Platform key.
	 * @param bool                 $with_media Resolve attachment paths.
	 * @param array<string, mixed> $options    Job options.
	 * @return Media_Package|WP_Error
	 */
	public function build( int $post_id, string $platform, bool $with_media, array $options = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'forwp_social_no_post', __( 'Post not found.', '4wp-social' ) );
		}

		$saved = self::saved( $post_id );
		$opt   = array();
		foreach ( self::sanitize( array() ) as $key => $empty ) {
			$value       = $options[ $key ] ?? null;
			$opt[ $key ] = ( null !== $value && '' !== $value && 0 !== $value ) ? $value : ( $saved[ $key ] ?? $empty );
		}

		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $post ) );
		$tags    = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$captions_path = $with_media && $opt['captions_id'] ? (string) get_attached_file( (int) $opt['captions_id'] ) : '';
		$cover_id      = $opt['cover_id'] ? (int) $opt['cover_id'] : (int) get_post_thumbnail_id( $post_id );

		$data = array(
			'post_id'           => $post_id,
			'platform'          => $platform,
			'entry'             => array( 'source' => 'post' ),
			'title'             => '' !== $opt['title'] ? $opt['title'] : get_the_title( $post ),
			'description'       => '' !== $opt['description'] ? $opt['description'] : trim( $excerpt . "\n\n" . get_permalink( $post ) ),
			'tags'              => is_array( $tags ) ? $tags : array(),
			'privacy'           => '' !== $opt['privacy'] ? $opt['privacy'] : (string) Settings::get( 'default_privacy', 'public' ),
			'captions_language' => Media_Package::language_from_file( $captions_path, (string) Settings::get( 'caption_language', 'en' ) ),
			'cover_ms'          => (int) $opt['cover_ms'],
		);

		if ( $with_media ) {
			$video = $opt['video_id'] ? (string) get_attached_file( (int) $opt['video_id'] ) : '';
			if ( '' === $video || ! is_readable( $video ) ) {
				return new WP_Error( 'forwp_social_no_video', __( 'Choose a video from the Media Library first.', '4wp-social' ) );
			}

			$data['video_path']    = $video;
			$data['captions_path'] = $captions_path;
			$data['cover_path']    = $cover_id ? (string) get_attached_file( $cover_id ) : '';
		}

		$media = new Media_Package( $data );

		/** This filter is documented in src/Modules/Posting/Package_Reader.php */
		$filtered = apply_filters( 'forwp_social_media_package', $media, $post_id, $platform );

		return $filtered instanceof Media_Package ? $filtered : $media;
	}
}
