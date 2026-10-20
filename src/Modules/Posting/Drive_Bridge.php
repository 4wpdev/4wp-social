<?php
/**
 * Entry points from 4WP Drive: import → copy media into WordPress; publish → queue API platforms.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Core\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to post status changes of posts imported from a meta.json package.
 */
final class Drive_Bridge {

	/**
	 * @return void
	 */
	public static function boot(): void {
		// The fat path: copy package media and texts into WordPress while Drive imports.
		add_action( 'forwp_drive_document_imported', array( Package_Importer::class, 'on_imported' ), 20, 2 );
		// After 4WP Drive (priority 10) has written original.url into meta.json. Auto-queue is off by default: the button decides.
		add_action( 'transition_post_status', array( self::class, 'on_status' ), 20, 3 );
	}

	/**
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_status( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof WP_Post ) {
			return;
		}

		// Jobs queued by the button while the post was a draft or scheduled go out now.
		( new Job_Repository() )->wake( (int) $post->ID );
		if ( ! Settings::get( 'auto_publish', true ) ) {
			return;
		}
		if ( ! ( new Package_Reader() )->is_package_post( (int) $post->ID ) ) {
			return;
		}

		( new Posting_Service() )->enqueue_post( (int) $post->ID );
	}
}
