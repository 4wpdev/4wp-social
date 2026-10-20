<?php
/**
 * Registered modules (contours).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Core;

use ForWP\Social\Contracts\Module_Interface;
use ForWP\Social\Modules\Feed\Feed_Module;
use ForWP\Social\Modules\Posting\Posting_Module;
use ForWP\Social\Modules\Share\Share_Module;
use ForWP\Social\Modules\Video\Video_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry for Share / Posting / Feed / Video.
 */
final class Module_Registry {

	/**
	 * @var array<string, Module_Interface>|null
	 */
	private static ?array $modules = null;

	/**
	 * @return array<string, Module_Interface>
	 */
	public static function all(): array {
		if ( null !== self::$modules ) {
			return self::$modules;
		}

		$map = array(
			Share_Module::SLUG   => new Share_Module(),
			Posting_Module::SLUG => new Posting_Module(),
			Feed_Module::SLUG    => new Feed_Module(),
			Video_Module::SLUG   => new Video_Module(),
		);

		/**
		 * Filter 4WP Social modules.
		 *
		 * @param array<string, Module_Interface> $map Modules keyed by slug.
		 */
		$filtered = apply_filters( 'forwp_social_modules', $map );

		self::$modules = array();
		foreach ( is_array( $filtered ) ? $filtered : $map as $slug => $module ) {
			if ( $module instanceof Module_Interface ) {
				self::$modules[ (string) $slug ] = $module;
			}
		}

		return self::$modules;
	}

	/**
	 * @param string $slug Module slug.
	 */
	public static function get( string $slug ): ?Module_Interface {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Boot enabled modules (init, priority 5).
	 *
	 * @return void
	 */
	public static function boot_enabled(): void {
		foreach ( self::all() as $slug => $module ) {
			if ( Settings::is_module_enabled( $slug ) ) {
				$module->boot();
			}
		}
	}

	/**
	 * Reset cache (tests).
	 */
	public static function reset(): void {
		self::$modules = null;
	}
}
