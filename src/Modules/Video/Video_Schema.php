<?php
/**
 * VideoObject JSON-LD on a single video page.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Video;

use ForWP\Social\Core\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Original file as contentUrl, YouTube as embedUrl, copies as sameAs, transcript for search.
 */
final class Video_Schema {

	/**
	 * @return void
	 */
	public static function boot(): void {
		if ( ! empty( Settings::get( 'video' )['json_ld'] ) ) {
			add_action( 'wp_head', array( self::class, 'render' ), 99 );
		}
	}

	/**
	 * @return void
	 */
	public static function render(): void {
		if ( ! is_singular( Video_Types::post_type() ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$data = self::build( $post );
		if ( empty( $data['thumbnailUrl'] ) ) {
			// Google requires a thumbnail; without it the markup is invalid.
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
		);
	}

	/**
	 * @param WP_Post $post Video post.
	 * @return array<string, mixed>
	 */
	public static function build( WP_Post $post ): array {
		$id          = (int) $post->ID;
		$description = wp_strip_all_tags( (string) get_the_excerpt( $post ) );

		$data = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => get_the_title( $post ),
			'description'  => '' !== $description ? $description : get_the_title( $post ),
			'thumbnailUrl' => (string) get_the_post_thumbnail_url( $post, 'full' ),
			'uploadDate'   => (string) get_post_time( 'c', true, $post ),
			'url'          => (string) get_permalink( $post ),
		);

		$duration = (int) get_post_meta( $id, 'duration', true );
		if ( $duration > 0 ) {
			$data['duration'] = 'PT' . ( $duration >= 60 ? intdiv( $duration, 60 ) . 'M' : '' ) . ( $duration % 60 ) . 'S';
		}

		$file = (int) get_post_meta( $id, 'video_file_id', true );
		if ( $file && wp_get_attachment_url( $file ) ) {
			$data['contentUrl'] = (string) wp_get_attachment_url( $file );
		}

		$youtube = (string) get_post_meta( $id, 'youtube_id', true );
		if ( '' !== $youtube ) {
			$data['embedUrl'] = 'https://www.youtube.com/embed/' . rawurlencode( $youtube );
		}

		$urls = get_post_meta( $id, 'platform_urls', true );
		if ( is_array( $urls ) && $urls ) {
			$data['sameAs'] = array_values( array_map( 'strval', $urls ) );
		}

		$transcript = (string) get_post_meta( $id, 'transcript', true );
		if ( '' !== $transcript ) {
			$data['transcript'] = $transcript;
		}

		/**
		 * Filter the VideoObject JSON-LD of a video post.
		 *
		 * @param array<string, mixed> $data VideoObject.
		 * @param WP_Post              $post Video post.
		 */
		return (array) apply_filters( 'forwp_social_video_schema', $data, $post );
	}
}
