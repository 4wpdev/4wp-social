<?php
/**
 * Outbound platform adapter (Posting module).
 *
 * Mirror of ForWP\Drive\Contracts\Storage_Source_Interface: Drive sources bring content in,
 * Social adapters take it out to a platform.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for publishing a package to one platform.
 */
interface Platform_Adapter_Interface {

	/**
	 * Platform key as used in meta.json "platforms" (domain, e.g. youtube.com).
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * Whether credentials and a connected account are present.
	 */
	public function is_ready(): bool;

	/**
	 * Start publishing. Asynchronous platforms return a processing result with a reference.
	 *
	 * @param Media_Package $package Package with local media paths.
	 */
	public function publish( Media_Package $package ): Publish_Result;

	/**
	 * Poll a processing publication.
	 *
	 * @param string        $reference Platform reference from publish() (e.g. TikTok publish_id).
	 * @param Media_Package $package   Package without media (metadata only).
	 */
	public function check( string $reference, Media_Package $package ): Publish_Result;
}
