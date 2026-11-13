<?php
/**
 * Registered outbound platform adapters.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Contracts\Article_Adapter_Interface;
use ForWP\Social\Contracts\Image_Capable_Interface;
use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Modules\Posting\Adapters\Bluesky_Adapter;
use ForWP\Social\Modules\Posting\Adapters\DevTo_Adapter;
use ForWP\Social\Modules\Posting\Adapters\Facebook_Reels_Adapter;
use ForWP\Social\Modules\Posting\Adapters\Instagram_Reels_Adapter;
use ForWP\Social\Modules\Posting\Adapters\Pinterest_Adapter;
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
	private static ?array $connectors = null;

	/**
	 * Adapters by platform key: only what a platform is bound to.
	 *
	 * @return array<string, Platform_Adapter_Interface>
	 */
	public static function all(): array {
		$bound = array();
		foreach ( Platform_Registry::all( false ) as $key => $platform ) {
			$connector = self::connectors()[ (string) ( $platform['connector'] ?? '' ) ] ?? null;
			if ( $connector ) {
				$bound[ (string) $key ] = $connector;
			}
		}

		return $bound;
	}

	/**
	 * Connectors of the plugin, bound or not, by their own slug.
	 *
	 * @return array<string, Platform_Adapter_Interface>
	 */
	public static function connectors(): array {
		if ( null !== self::$connectors ) {
			return self::$connectors;
		}

		$map = array(
			YouTube_Adapter::SLUG         => new YouTube_Adapter(),
			TikTok_Adapter::SLUG          => new TikTok_Adapter(),
			Bluesky_Adapter::SLUG         => new Bluesky_Adapter(),
			DevTo_Adapter::SLUG           => new DevTo_Adapter(),
			Facebook_Reels_Adapter::SLUG  => new Facebook_Reels_Adapter(),
			Instagram_Reels_Adapter::SLUG => new Instagram_Reels_Adapter(),
			Pinterest_Adapter::SLUG       => new Pinterest_Adapter(),
		);

		/**
		 * Filter platform adapters (Posting module).
		 *
		 * @param array<string, Platform_Adapter_Interface> $map Adapters keyed by meta.json platform key.
		 */
		$filtered         = apply_filters( 'forwp_social_adapters', $map );
		self::$connectors = array();
		foreach ( is_array( $filtered ) ? $filtered : $map as $slug => $adapter ) {
			if ( $adapter instanceof Platform_Adapter_Interface ) {
				self::$connectors[ (string) $slug ] = $adapter;
			}
		}

		return self::$connectors;
	}

	/**
	 * @param string $slug Platform key.
	 */
	public static function get( string $slug ): ?Platform_Adapter_Interface {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Whether the platform publishes text articles from a package file instead of video.
	 *
	 * @param string $slug Platform key.
	 */
	public static function is_article( string $slug ): bool {
		return self::get( $slug ) instanceof Article_Adapter_Interface;
	}

	/**
	 * Whether the platform can publish an image when the package has no video (Pinterest).
	 *
	 * @param string $slug Platform key.
	 */
	public static function accepts_image( string $slug ): bool {
		return self::get( $slug ) instanceof Image_Capable_Interface;
	}

	/**
	 * Reset cache (tests).
	 */
	public static function reset(): void {
		self::$connectors = null;
	}
}
