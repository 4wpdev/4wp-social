<?php
/**
 * Plugin settings (modules on/off, posting defaults).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes forwp_social_settings.
 */
final class Settings {

	public const OPTION_KEY = 'forwp_social_settings';

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'modules'          => array(
				'share'   => false,
				'posting' => true,
				'feed'    => false,
				'video'   => true,
			),
			'video'            => array(
				'post_type' => 'video',
				'taxonomy'  => 'video-series',
				'slug'      => 'video',
				'json_ld'   => true,
			),
			'auto_publish'     => false,
			'delay_minutes'    => 0,
			'default_privacy'  => 'public',
			'caption_language' => 'en',
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored   = get_option( self::OPTION_KEY, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();

		$settings            = array_merge( $defaults, $stored );
		$settings['modules'] = array_merge(
			$defaults['modules'],
			isset( $stored['modules'] ) && is_array( $stored['modules'] ) ? $stored['modules'] : array()
		);
		$settings['video']   = array_merge(
			$defaults['video'],
			isset( $stored['video'] ) && is_array( $stored['video'] ) ? $stored['video'] : array()
		);

		return $settings;
	}

	/**
	 * One setting.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback when missing.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Whether a module is switched on.
	 *
	 * @param string $slug Module slug.
	 */
	public static function is_module_enabled( string $slug ): bool {
		$modules = self::all()['modules'];

		return ! empty( $modules[ $slug ] );
	}

	/**
	 * Save sanitized settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public static function save( array $input ): void {
		$current = self::all();

		$modules = array();
		foreach ( array_keys( $current['modules'] ) as $slug ) {
			$modules[ $slug ] = ! empty( $input['modules'][ $slug ] );
		}

		$privacy = sanitize_key( (string) ( $input['default_privacy'] ?? $current['default_privacy'] ) );
		if ( ! in_array( $privacy, array( 'public', 'unlisted', 'private' ), true ) ) {
			$privacy = 'public';
		}

		$language = sanitize_key( (string) ( $input['caption_language'] ?? $current['caption_language'] ) );

		$video_in = isset( $input['video'] ) && is_array( $input['video'] ) ? $input['video'] : array();
		$video    = array( 'json_ld' => ! empty( $video_in['json_ld'] ) );
		foreach ( array( 'post_type' => 20, 'taxonomy' => 32, 'slug' => 200 ) as $key => $max ) {
			$value         = 'slug' === $key
				? sanitize_title( (string) ( $video_in[ $key ] ?? '' ) )
				: sanitize_key( (string) ( $video_in[ $key ] ?? '' ) );
			$video[ $key ] = ( '' !== $value && strlen( $value ) <= $max ) ? $value : $current['video'][ $key ];
		}

		update_option(
			self::OPTION_KEY,
			array(
				'modules'          => $modules,
				'video'            => $video,
				'auto_publish'     => ! empty( $input['auto_publish'] ),
				'delay_minutes'    => max( 0, min( 10080, (int) ( $input['delay_minutes'] ?? 0 ) ) ),
				'default_privacy'  => $privacy,
				'caption_language' => '' !== $language ? $language : 'en',
			),
			false
		);
	}
}
