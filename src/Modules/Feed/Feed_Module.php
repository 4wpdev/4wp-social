<?php
/**
 * Feed contour: social feeds shown on the site.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Feed;

use ForWP\Social\Contracts\Module_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Contour only: provider registry and contract. Instagram / Facebook / TikTok / YouTube feeds come next.
 */
final class Feed_Module implements Module_Interface {

	public const SLUG = 'feed';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return __( 'Feed', '4wp-social' );
	}

	public function get_description(): string {
		return __( 'Show your Instagram, Facebook, TikTok or YouTube feed on the site, with switching and fine tuning.', '4wp-social' );
	}

	public function is_implemented(): bool {
		return false;
	}

	public function boot(): void {
		// Contour: providers register through forwp_social_feed_providers; blocks are not built yet.
	}
}
