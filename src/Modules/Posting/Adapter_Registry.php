<?php
/**
 * Registered outbound platform adapters.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Modules\Posting\Adapters\TikTok_Adapter;
use ForWP\Social\Modules\Posting\Adapters\YouTube_Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry, same shape as ForWP\Drive\Source_Registry.
 */
final class Adapter_Registry {

	/**
	 * @var array<string, Platform_Adapter_Interface>|null
	 */
	private static ?array $adapters = null;

	/**
	 * @return array<string, Platform_Adapter_Interface>
	 */
	public static function all(): array {
		if ( null !== self::$adapters ) {
			return self::$adapters;
		}

		$map = array(
			YouTube_Adapter::SLUG => new YouTube_Adapter(),
			TikTok_Adapter::SLUG  => new TikTok_Adapter(),
		);

		/**
		 * Filter platform adapters (Posting module).
		 *
		 * @param array<string, Platform_Adapter_Interface> $map Adapters keyed by meta.json platform key.
		 */
		$filtered       = apply_filters( 'forwp_social_adapters', $map );
		self::$adapters = array();
		foreach ( is_array( $filtered ) ? $filtered : $map as $slug => $adapter ) {
			if ( $adapter instanceof Platform_Adapter_Interface ) {
				self::$adapters[ (string) $slug ] = $adapter;
			}
		}

		return self::$adapters;
	}

	/**
	 * @param string $slug Platform key.
	 */
	public static function get( string $slug ): ?Platform_Adapter_Interface {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Reset cache (tests).
	 */
	public static function reset(): void {
		self::$adapters = null;
	}
}
