<?php
/**
 * "Post video" block: the video of the current post, for the single video template.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Video;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the post's video (first core/video in the content, else the video file meta, else YouTube)
 * and hides that same video from Post Content so it is not shown twice.
 */
final class Post_Video_Block {

	public const NAME = 'forwp-social/post-video';

	/**
	 * Posts whose first core/video moved into this block and must be skipped in Post Content.
	 *
	 * @var array<int, true>
	 */
	private static $skip_in_content = array();

	/**
	 * True while this block renders its own copy of the video.
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'render_block_core/video', array( self::class, 'skip_moved_video' ), 10, 2 );
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		$css  = FORWP_SOCIAL_PATH . 'assets/post-video.css';
		$js   = FORWP_SOCIAL_PATH . 'assets/post-video-block.js';
		$view = FORWP_SOCIAL_PATH . 'assets/post-video-view.js';

		wp_register_style(
			'forwp-social-post-video',
			FORWP_SOCIAL_URL . 'assets/post-video.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : FORWP_SOCIAL_VERSION
		);
		wp_register_script(
			'forwp-social-post-video-editor',
			FORWP_SOCIAL_URL . 'assets/post-video-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			is_readable( $js ) ? (string) filemtime( $js ) : FORWP_SOCIAL_VERSION,
			true
		);
		wp_register_script(
			'forwp-social-post-video-view',
			FORWP_SOCIAL_URL . 'assets/post-video-view.js',
			array(),
			is_readable( $view ) ? (string) filemtime( $view ) : FORWP_SOCIAL_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		register_block_type(
			self::NAME,
			array(
				'api_version'         => 3,
				'title'               => __( 'Post video', '4wp-social' ),
				'description'         => __( 'The video of the current post (shorts 9:16 or landscape).', '4wp-social' ),
				'category'            => 'media',
				'icon'                => 'video-alt3',
				'uses_context'        => array( 'postId', 'postType' ),
				'attributes'          => array(
					'maxWidth' => array(
						'type'    => 'number',
						'default' => 400,
					),
					// player = native controls (single page); card = tap to play/pause (archives, loops).
					'variant'  => array(
						'type'    => 'string',
						'enum'    => array( 'player', 'card' ),
						'default' => 'player',
					),
				),
				'supports'            => array(
					'html'    => false,
					'align'   => false,
					'spacing' => array( 'margin' => true ),
				),
				'style_handles'       => array( 'forwp-social-post-video' ),
				'view_script_handles' => array( 'forwp-social-post-video-view' ),
				'editor_script'       => 'forwp-social-post-video-editor',
				'render_callback'     => array( self::class, 'render' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner content (unused).
	 * @param WP_Block|null        $block      Block instance.
	 */
	public static function render( $attributes, $content = '', $block = null ): string {
		unset( $content );
		$post_id = $block instanceof WP_Block && ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return '';
		}

		$format  = (string) get_post_meta( $post_id, 'video_format', true );
		$is_card = 'card' === ( $attributes['variant'] ?? 'player' );
		$player  = $is_card ? self::card_html( $post_id ) : self::player_html( $post_id );
		if ( '' === $player ) {
			return '';
		}

		$max     = isset( $attributes['maxWidth'] ) ? max( 200, (int) $attributes['maxWidth'] ) : 400;
		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => ( 'landscape' === $format ? 'is-landscape' : 'is-shorts' ) . ( $is_card ? ' is-card' : '' ),
				'style' => '--forwp-post-video-max:' . $max . 'px;',
			)
		);

		return '<div ' . $wrapper . '>' . $player . '</div>';
	}

	/**
	 * Hide the first core/video of a post once this block has shown it.
	 *
	 * @param string               $html  Rendered video block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function skip_moved_video( $html, $block ) {
		unset( $block );
		if ( self::$rendering ) {
			return $html;
		}

		$post_id = (int) get_the_ID();
		if ( $post_id > 0 && isset( self::$skip_in_content[ $post_id ] ) ) {
			unset( self::$skip_in_content[ $post_id ] );

			return '';
		}

		return $html;
	}

	/**
	 * Player markup: content video, then the video file meta, then the YouTube copy.
	 *
	 * @param int $post_id Post id.
	 */
	private static function player_html( int $post_id ): string {
		$video = self::first_video_block( parse_blocks( (string) get_post_field( 'post_content', $post_id ) ) );
		if ( null !== $video ) {
			self::$rendering = true;
			$html            = render_block( $video );
			self::$rendering = false;
			if ( '' !== trim( $html ) ) {
				self::$skip_in_content[ $post_id ] = true;

				return self::with_poster( $html, $post_id );
			}
		}

		$file = (int) get_post_meta( $post_id, 'video_file_id', true );
		$url  = $file ? wp_get_attachment_url( $file ) : '';
		if ( is_string( $url ) && '' !== $url ) {
			$poster = (string) get_the_post_thumbnail_url( $post_id, 'large' );

			return sprintf(
				'<figure class="wp-block-video"><video controls playsinline preload="metadata" src="%1$s"%2$s></video></figure>',
				esc_url( $url ),
				'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : ''
			);
		}

		$youtube = (string) get_post_meta( $post_id, 'youtube_id', true );
		if ( '' !== $youtube && preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $youtube ) ) {
			return sprintf(
				'<figure class="wp-block-embed is-provider-youtube"><iframe src="%1$s" title="%2$s" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></figure>',
				esc_url( 'https://www.youtube-nocookie.com/embed/' . $youtube ),
				esc_attr( get_the_title( $post_id ) )
			);
		}

		return '';
	}

	/**
	 * Card player: poster with a play button; tap plays, tap again pauses (no link to the post).
	 *
	 * @param int $post_id Post id.
	 */
	private static function card_html( int $post_id ): string {
		$src = self::video_src( $post_id );
		if ( '' === $src ) {
			// YouTube-only video: the embed has its own play button.
			return self::player_html( $post_id );
		}

		$poster = (string) get_the_post_thumbnail_url( $post_id, 'medium_large' );
		$label  = sprintf(
			/* translators: %s: video title */
			__( 'Play: %s', '4wp-social' ),
			get_the_title( $post_id )
		);

		return sprintf(
			'<figure class="wp-block-video"><video playsinline loop preload="none" src="%1$s"%2$s></video><button type="button" class="forwp-post-video__toggle" aria-label="%3$s" aria-pressed="false"><span class="forwp-post-video__icon" aria-hidden="true"></span></button></figure>',
			esc_url( $src ),
			'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			esc_attr( $label )
		);
	}

	/**
	 * Video file URL: first core/video of the content, else the video file meta.
	 *
	 * @param int $post_id Post id.
	 */
	private static function video_src( int $post_id ): string {
		$video = self::first_video_block( parse_blocks( (string) get_post_field( 'post_content', $post_id ) ) );
		if ( null !== $video ) {
			if ( ! empty( $video['attrs']['id'] ) ) {
				$url = wp_get_attachment_url( (int) $video['attrs']['id'] );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
			$tags = new \WP_HTML_Tag_Processor( (string) ( $video['innerHTML'] ?? '' ) );
			if ( $tags->next_tag( 'video' ) && is_string( $tags->get_attribute( 'src' ) ) ) {
				return (string) $tags->get_attribute( 'src' );
			}
		}

		$file = (int) get_post_meta( $post_id, 'video_file_id', true );
		$url  = $file ? wp_get_attachment_url( $file ) : '';

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Featured image as poster when the video block has none.
	 *
	 * @param string $html    Video block HTML.
	 * @param int    $post_id Post id.
	 */
	private static function with_poster( string $html, int $post_id ): string {
		$poster = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		$tags   = new \WP_HTML_Tag_Processor( $html );
		if ( '' === $poster || ! $tags->next_tag( 'video' ) || null !== $tags->get_attribute( 'poster' ) ) {
			return $html;
		}
		$tags->set_attribute( 'poster', $poster );
		$tags->set_attribute( 'preload', 'metadata' );
		$tags->set_attribute( 'playsinline', true );

		return $tags->get_updated_html();
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<string, mixed>|null
	 */
	private static function first_video_block( array $blocks ): ?array {
		foreach ( $blocks as $block ) {
			if ( 'core/video' === ( $block['blockName'] ?? '' ) ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::first_video_block( $block['innerBlocks'] );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}
}
