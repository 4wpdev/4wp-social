<?php
/**
 * Video contour: every published video is a post on the site (the original), platforms are copies.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Video;

use ForWP\Social\Contracts\Module_Interface;
use ForWP\Social\Core\Publish_Result;
use ForWP\Social\Modules\Posting\Package_Reader;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Video CPT + series taxonomy + native meta, VideoObject JSON-LD, posting source for the CPT.
 */
final class Video_Module implements Module_Interface {

	public const SLUG = 'video';

	/**
	 * Hash of the registered slugs; rewrite rules are flushed when it changes.
	 */
	private const REWRITE_OPTION = 'forwp_social_video_rewrite';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return __( 'Video', '4wp-social' );
	}

	public function get_description(): string {
		return __( 'A Video post type: the site keeps the original file, poster, transcript and VideoObject schema; platforms get copies.', '4wp-social' );
	}

	public function is_implemented(): bool {
		return true;
	}

	public function boot(): void {
		Video_Types::register();
		Video_Schema::boot();
		Post_Video_Block::boot();

		add_action( 'init', array( self::class, 'maybe_flush_rewrite' ), 99 );
		add_action( 'save_post_' . Video_Types::post_type(), array( self::class, 'fill_transcript' ), 20, 2 );
		add_filter( 'forwp_social_post_types', array( self::class, 'posting_types' ) );
		add_filter( 'forwp_social_post_media', array( self::class, 'post_media' ), 10, 2 );
		add_action( 'forwp_social_published', array( self::class, 'remember_platform' ), 10, 3 );
		add_filter( 'forwp_drive_import_meta_fields', array( self::class, 'drive_fields' ) );
		add_action( 'forwp_social_package_imported', array( self::class, 'fill_tags' ), 10, 2 );
	}

	/**
	 * Front-matter lines a 4WP Drive package can map to video meta.
	 *
	 * @param array<int, array{slug: string, meta_key: string, label: string}> $fields Drive fields.
	 * @return array<int, array{slug: string, meta_key: string, label: string}>
	 */
	public static function drive_fields( $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();
		foreach ( array(
			'episode_code' => __( 'Episode code (Video)', '4wp-social' ),
			'source_url'   => __( 'Source URL (Video)', '4wp-social' ),
			'video_format' => __( 'Video format (Video)', '4wp-social' ),
		) as $key => $label ) {
			$fields[] = array(
				'slug'     => $key,
				'meta_key' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Field map, not a query.
				'label'    => $label,
			);
		}

		return $fields;
	}

	/**
	 * Flush rewrite rules once after the post type / taxonomy / slug changed.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite(): void {
		// "2": rule order changed (series before the CPT); bump to re-flush existing sites.
		$hash = md5( '2|' . Video_Types::post_type() . '|' . Video_Types::taxonomy() . '|' . Video_Types::slug() );
		if ( get_option( self::REWRITE_OPTION ) === $hash ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $hash, false );
	}

	/**
	 * The editor box "4WP Social" on the video post type.
	 *
	 * @param array<int, string> $types Post types.
	 * @return array<int, string>
	 */
	public static function posting_types( $types ): array {
		$types   = is_array( $types ) ? $types : array();
		$types[] = Video_Types::post_type();

		return array_values( array_unique( $types ) );
	}

	/**
	 * Video posts publish their own file, captions and poster when the editor box has no choice.
	 *
	 * @param array<string, mixed> $data    Saved editor choices.
	 * @param int                  $post_id Post id.
	 * @return array<string, mixed>
	 */
	public static function post_media( $data, $post_id ): array {
		$data    = is_array( $data ) ? $data : array();
		$post_id = (int) $post_id;
		if ( Video_Types::post_type() !== get_post_type( $post_id ) ) {
			return $data;
		}

		$own = array(
			'video_id'    => (int) get_post_meta( $post_id, 'video_file_id', true ),
			'captions_id' => (int) get_post_meta( $post_id, 'captions_file_id', true ),
			'cover_id'    => (int) get_post_thumbnail_id( $post_id ),
		);
		foreach ( $own as $key => $id ) {
			if ( empty( $data[ $key ] ) && $id ) {
				$data[ $key ] = $id;
			}
		}

		return $data;
	}

	/**
	 * Site tags from the platform copy (youtube.com.md "## Tags") and the series, when the post has none.
	 *
	 * @param int                                $post_id  Post id.
	 * @param array<string, array<string, mixed>> $snapshot Platforms snapshot.
	 * @return void
	 */
	public static function fill_tags( $post_id, $snapshot ): void {
		$post_id = (int) $post_id;
		if ( Video_Types::post_type() !== get_post_type( $post_id ) || ! is_array( $snapshot ) ) {
			return;
		}
		self::fill_series( $post_id );
		if ( ! empty( wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) ) ) ) {
			return;
		}

		$tags = self::copy_tags( $snapshot );
		if ( ! empty( $tags ) ) {
			wp_set_post_terms( $post_id, $tags, 'post_tag', false );
		}
	}

	/**
	 * Series term from meta.json "package.series" when the video has none.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function fill_series( int $post_id ): void {
		$taxonomy = Video_Types::taxonomy();
		if ( ! empty( wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ) ) ) {
			return;
		}

		$package = ( new Package_Reader() )->package( $post_id );
		$series  = is_array( $package ) ? trim( (string) ( $package['package']['series'] ?? '' ) ) : '';
		if ( '' === $series ) {
			return;
		}

		$term = term_exists( $series, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $series, $taxonomy );
		}
		if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
			wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), $taxonomy, false );
		}
	}

	/**
	 * Tags of the first platform copy that has them, YouTube first.
	 *
	 * @param array<string, array<string, mixed>> $snapshot Platforms snapshot.
	 * @return array<int, string>
	 */
	public static function copy_tags( array $snapshot ): array {
		$order = array_unique( array_merge( array( 'youtube.com' ), array_keys( $snapshot ) ) );
		foreach ( $order as $platform ) {
			$tags = $snapshot[ $platform ]['copy']['tags'] ?? array();
			$tags = is_array( $tags ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $tags ) ) ) ) : array();
			if ( ! empty( $tags ) ) {
				return $tags;
			}
		}

		return array();
	}

	/**
	 * Transcript from the captions file when the field is empty.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function fill_transcript( $post_id, $post ): void {
		unset( $post );
		$post_id = (int) $post_id;
		if ( wp_is_post_revision( $post_id ) || '' !== trim( (string) get_post_meta( $post_id, 'transcript', true ) ) ) {
			return;
		}

		$captions = (int) get_post_meta( $post_id, 'captions_file_id', true );
		$file     = $captions ? (string) get_attached_file( $captions ) : '';
		if ( '' === $file || ! is_readable( $file ) ) {
			return;
		}

		$text = Srt::to_text( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment.
		if ( '' !== $text ) {
			update_post_meta( $post_id, 'transcript', $text );
		}
	}

	/**
	 * Keep where each copy of the video lives.
	 *
	 * @param int            $post_id  Post id.
	 * @param string         $platform Platform key (youtube.com, tiktok.com).
	 * @param Publish_Result $result   Result.
	 * @return void
	 */
	public static function remember_platform( $post_id, $platform, $result ): void {
		$post_id = (int) $post_id;
		if ( Video_Types::post_type() !== get_post_type( $post_id ) || ! $result instanceof Publish_Result ) {
			return;
		}

		$urls = get_post_meta( $post_id, 'platform_urls', true );
		$urls = is_array( $urls ) ? $urls : array();
		if ( '' !== $result->remote_url() ) {
			$urls[ (string) $platform ] = $result->remote_url();
			update_post_meta( $post_id, 'platform_urls', $urls );
		}

		if ( 'youtube.com' === $platform && '' !== $result->remote_id() ) {
			update_post_meta( $post_id, 'youtube_id', $result->remote_id() );
		}
	}
}
