<?php
/**
 * Feed provider contract (Feed module, contour).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A platform whose feed can be shown on the site.
 */
interface Feed_Provider_Interface {

	/**
	 * Platform key (e.g. instagram.com, youtube.com).
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * Whether the provider is configured.
	 */
	public function is_ready(): bool;

	/**
	 * Latest items, newest first.
	 *
	 * @param array<string, mixed> $args Provider arguments (limit, source id, …).
	 * @return array<int, array{id: string, url: string, title: string, media_url: string, published_at: string}>|WP_Error
	 */
	public function fetch( array $args = array() );
}
