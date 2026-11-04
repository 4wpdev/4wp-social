<?php
/**
 * "Social" column in the post lists. Reads only the local index.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

defined( 'ABSPATH' ) || exit;

/**
 * Platform icon + status marker, linked to the copy on the platform.
 */
final class Status_Column {

	public const COLUMN = 'forwp_social';

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Post types with the column. `post` is here without getting the media box of forwp_social_post_types.
	 *
	 * @return array<int, string>
	 */
	public static function post_types(): array {
		/**
		 * Filter post types that show cross-posting statuses.
		 *
		 * @param array<int, string> $types Post types.
		 */
		$types = apply_filters( 'forwp_social_status_post_types', array_merge( array( 'post' ), Post_Metabox::post_types() ) );

		return is_array( $types ) ? array_values( array_unique( array_map( 'strval', $types ) ) ) : array();
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( self::class, 'add' ) );
			add_action( "manage_{$type}_posts_custom_column", array( self::class, 'render' ), 10, 2 );
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function add( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'Social', '4wp-social' );

		return $columns;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Post id.
	 * @return void
	 */
	public static function render( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$index = Status_Index::get( (int) $post_id );
		if ( ! $index ) {
			if ( ( new Package_Reader() )->is_package_post( (int) $post_id ) ) {
				echo '<span class="forwp-social-muted">—</span>';
			}
			return;
		}

		$registry = Platform_Registry::all( false );
		foreach ( $index as $key => $entry ) {
			$key      = (string) $key;
			$platform = $registry[ $key ] ?? array( 'label' => $key );
			$status   = (string) ( $entry['status'] ?? '' );
			$title    = (string) $platform['label'] . ': ' . self::status_label( $status );
			if ( '' !== (string) ( $entry['run_at'] ?? '' ) && in_array( $status, array( Status_Index::PLANNED, Job_Repository::QUEUED ), true ) ) {
				$title .= ' · ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( (string) $entry['run_at'] ) );
			}

			$url   = (string) ( $entry['url'] ?? '' );
			$open  = '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"' : '<span';
			$close = '' !== $url ? '</a>' : '</span>';

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $open and $close are built above with esc_url().
			echo $open . ' class="forwp-social-chip" title="' . esc_attr( $title ) . '">';
			echo Platform_Icons::render( $key );
			echo self::marker( $status );
			echo '<span class="screen-reader-text">' . esc_html( $title ) . '</span>';
			echo $close;
			// phpcs:enable
		}
	}

	/**
	 * Status icon: a shape and a color, the text alternative is the caller's.
	 *
	 * @param string $status Job status, or publish / update for site content.
	 */
	public static function marker( string $status ): string {
		$icons = array(
			Job_Repository::PUBLISHED    => array( 'dashicons-yes-alt', 'done' ),
			Job_Repository::QUEUED       => array( 'dashicons-clock', 'planned' ),
			Status_Index::PLANNED        => array( 'dashicons-clock', 'planned' ),
			Job_Repository::PROCESSING   => array( 'dashicons-hourglass', 'planned' ),
			Job_Repository::FAILED       => array( 'dashicons-warning', 'failed' ),
			Status_Index::MANUAL_PENDING => array( 'dashicons-marker', 'waiting' ),
			'publish'                    => array( 'dashicons-plus-alt2', 'site' ),
			'update'                     => array( 'dashicons-update', 'site' ),
		);
		if ( ! isset( $icons[ $status ] ) ) {
			return '';
		}

		return '<span class="dashicons ' . $icons[ $status ][0] . ' forwp-social-marker forwp-social-marker--' . $icons[ $status ][1] . '" aria-hidden="true"></span>';
	}

	/**
	 * @param string $status Status.
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			Job_Repository::PUBLISHED    => __( 'published', '4wp-social' ),
			Job_Repository::QUEUED       => __( 'queued', '4wp-social' ),
			Status_Index::PLANNED        => __( 'planned', '4wp-social' ),
			Job_Repository::PROCESSING   => __( 'processing', '4wp-social' ),
			Job_Repository::FAILED       => __( 'failed', '4wp-social' ),
			Status_Index::MANUAL_PENDING => __( 'waiting for manual posting', '4wp-social' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$css = FORWP_SOCIAL_PATH . 'assets/admin.css';
		wp_enqueue_style( 'forwp-social-admin', FORWP_SOCIAL_URL . 'assets/admin.css', array(), is_readable( $css ) ? (string) filemtime( $css ) : FORWP_SOCIAL_VERSION );
	}
}
