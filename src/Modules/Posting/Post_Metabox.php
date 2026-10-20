<?php
/**
 * "4WP Social" box in the post editor: choose media and publish by button.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Classic meta box (works in the block editor too). The button calls REST forwp-social/v1/jobs.
 */
final class Post_Metabox {

	private const NONCE = 'forwp_social_metabox';

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register' ) );
		add_action( 'save_post', array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Post types with the box.
	 *
	 * @return array<int, string>
	 */
	public static function post_types(): array {
		/**
		 * Filter post types that get the 4WP Social box.
		 * Empty by default: the Video module adds its post type; other types opt in here.
		 *
		 * @param array<int, string> $types Post types.
		 */
		$types = apply_filters( 'forwp_social_post_types', array() );

		return is_array( $types ) ? array_values( array_map( 'strval', $types ) ) : array();
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}

		$types = self::post_types();
		if ( empty( $types ) ) {
			// add_meta_box() with no screens would fall back to the current screen.
			return;
		}

		add_meta_box( 'forwp-social', __( '4WP Social', '4wp-social' ), array( self::class, 'render' ), $types, 'side' );
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$saved      = Post_Reader::saved( (int) $post->ID );
		$service    = new Posting_Service();
		$published  = $service->published_map( (int) $post->ID );
		$jobs       = new Job_Repository();
		$is_package = ( new Package_Reader() )->is_package_post( (int) $post->ID );
		$adapters   = Adapter_Registry::all();

		include FORWP_SOCIAL_PATH . 'views/post-metabox.php';
	}

	/**
	 * Save media choices.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function save( $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['forwp_social'] ) && is_array( $_POST['forwp_social'] ) ? map_deep( wp_unslash( $_POST['forwp_social'] ), 'sanitize_textarea_field' ) : array();
		update_post_meta( (int) $post_id, Post_Reader::META, Post_Reader::sanitize( $raw ) );
	}

	/**
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();
		$css = FORWP_SOCIAL_PATH . 'assets/admin.css';
		$js  = FORWP_SOCIAL_PATH . 'assets/post-metabox.js';
		wp_enqueue_style( 'forwp-social-admin', FORWP_SOCIAL_URL . 'assets/admin.css', array(), is_readable( $css ) ? (string) filemtime( $css ) : FORWP_SOCIAL_VERSION );
		wp_enqueue_script( 'forwp-social-metabox', FORWP_SOCIAL_URL . 'assets/post-metabox.js', array( 'wp-api-fetch', 'jquery' ), is_readable( $js ) ? (string) filemtime( $js ) : FORWP_SOCIAL_VERSION, true );
		wp_localize_script(
			'forwp-social-metabox',
			'forwpSocialMetabox',
			array(
				'i18n' => array(
					'choose'    => __( 'Choose file', '4wp-social' ),
					'pick'      => __( 'Select at least one platform.', '4wp-social' ),
					'queued'    => __( 'Queued. Status updates every minute.', '4wp-social' ),
					'queuedBtn' => __( 'Queued…', '4wp-social' ),
					'working'   => __( 'Publishing…', '4wp-social' ),
					'doneBtn'   => __( 'Published ✓', '4wp-social' ),
					'retryBtn'  => __( 'Retry', '4wp-social' ),
					'posted'    => __( 'posted', '4wp-social' ),
					'skipped'   => __( 'Skipped', '4wp-social' ),
					'failed'    => __( 'Request failed.', '4wp-social' ),
					'saveNote'  => __( 'Unsaved post: media choices are sent with the request.', '4wp-social' ),
					'linked'    => __( 'Linked:', '4wp-social' ),
					'unlinked'  => __( 'Unlinked. Reload the page to link again.', '4wp-social' ),
					'notFound'  => __( 'Nothing found by title. Paste the video ID or link below.', '4wp-social' ),
				),
			)
		);
	}
}
