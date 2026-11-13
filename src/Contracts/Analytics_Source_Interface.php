<?php
/**
 * Analytics source: reads statistics from one platform.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One analytics source (YouTube, TikTok, Facebook, Instagram …).
 */
interface Analytics_Source_Interface {

	/**
	 * Key, as in the platform registry (youtube.com).
	 */
	public function slug(): string;

	/**
	 * Human label.
	 */
	public function label(): string;

	/**
	 * Status: `ready` (connected), `not_connected`, `planned` (not built yet).
	 */
	public function status(): string;

	/**
	 * What the source reads and which permissions it needs.
	 */
	public function note(): string;

	/**
	 * Raw data. Each part is the API answer or `{ "error": "..." }`.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function fetch();
}
