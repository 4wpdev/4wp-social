<?php
/**
 * Share contour: visitors share a page to social networks.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Share;

use ForWP\Social\Contracts\Module_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Contour only: service registry and contract. Buttons / smart sharing come next.
 */
final class Share_Module implements Module_Interface {

	public const SLUG = 'share';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return __( 'Share', '4wp-social' );
	}

	public function get_description(): string {
		return __( 'Visitors share your pages: share buttons, later smart sharing.', '4wp-social' );
	}

	public function is_implemented(): bool {
		return false;
	}

	public function boot(): void {
		// Contour: services register through forwp_social_share_services; rendering is not built yet.
	}
}
