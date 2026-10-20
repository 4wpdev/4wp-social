<?php
/**
 * Module (contour) contract: Share, Posting, Feed.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A switchable part of 4WP Social.
 */
interface Module_Interface {

	/**
	 * Unique module slug (share|posting|feed).
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * One line for the Modules screen.
	 */
	public function get_description(): string;

	/**
	 * Whether the module does real work yet (false = contour only).
	 */
	public function is_implemented(): bool;

	/**
	 * Register hooks. Called on init only when the module is enabled.
	 *
	 * @return void
	 */
	public function boot(): void;
}
