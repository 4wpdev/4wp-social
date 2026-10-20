<?php
/**
 * Share service contract (Share module, contour).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A network a visitor can share a page to.
 */
interface Share_Service_Interface {

	/**
	 * Service key (e.g. x.com, facebook.com, telegram.org).
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * Share intent URL for a page.
	 *
	 * @param string $url   Page URL.
	 * @param string $title Page title.
	 */
	public function get_share_url( string $url, string $title ): string;
}
