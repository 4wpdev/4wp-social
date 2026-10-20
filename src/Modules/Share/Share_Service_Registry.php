<?php
/**
 * Registered share services.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Share;

use ForWP\Social\Contracts\Share_Service_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Empty until the Share module is built; third parties can add services already.
 */
final class Share_Service_Registry {

	/**
	 * @return array<string, Share_Service_Interface>
	 */
	public static function all(): array {
		/**
		 * Filter share services.
		 *
		 * @param array<string, Share_Service_Interface> $services Services keyed by slug.
		 */
		$services = apply_filters( 'forwp_social_share_services', array() );

		return array_filter(
			is_array( $services ) ? $services : array(),
			static function ( $service ) {
				return $service instanceof Share_Service_Interface;
			}
		);
	}
}
