<?php
/**
 * Registered feed providers.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Feed;

use ForWP\Social\Contracts\Feed_Provider_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Empty until the Feed module is built; third parties can add providers already.
 */
final class Feed_Provider_Registry {

	/**
	 * @return array<string, Feed_Provider_Interface>
	 */
	public static function all(): array {
		/**
		 * Filter feed providers.
		 *
		 * @param array<string, Feed_Provider_Interface> $providers Providers keyed by platform.
		 */
		$providers = apply_filters( 'forwp_social_feed_providers', array() );

		return array_filter(
			is_array( $providers ) ? $providers : array(),
			static function ( $provider ) {
				return $provider instanceof Feed_Provider_Interface;
			}
		);
	}
}
