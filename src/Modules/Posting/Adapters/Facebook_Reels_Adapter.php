<?php
/**
 * Facebook Page Reels: start → upload → finish → poll status (ADR 0004).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Api\Meta_Client;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\Meta_OAuth_Provider;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Asynchronous: publish() returns processing with the video ID, check() polls until publishing completes.
 */
final class Facebook_Reels_Adapter implements Platform_Adapter_Interface {

	public const SLUG = 'facebook.com';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Facebook Reels';
	}

	public function is_ready(): bool {
		return Credentials_Store::has( Meta_OAuth_Provider::SLUG ) && OAuth_Controller::instance()->is_connected( Meta_OAuth_Provider::SLUG );
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $package->has_video() ) {
			return Publish_Result::failed( __( 'No video file to upload.', '4wp-social' ), false );
		}

		$page = self::page();
		if ( is_string( $page ) ) {
			return Publish_Result::failed( $page, false );
		}

		$client = new Meta_Client( $page['access_token'] );
		$start  = $client->reel_start( $page['id'] );
		if ( is_wp_error( $start ) ) {
			return Publish_Result::failed( $start->get_error_message() );
		}

		$uploaded = $client->reel_upload( $start['video_id'], $package->video_path(), $start['upload_url'] );
		if ( is_wp_error( $uploaded ) ) {
			return Publish_Result::failed( $uploaded->get_error_message() );
		}

		$finished = $client->reel_finish( $page['id'], $start['video_id'], self::caption( $package ), self::video_state( $package->privacy() ) );
		if ( is_wp_error( $finished ) ) {
			return Publish_Result::failed( $finished->get_error_message() );
		}

		return Publish_Result::processing( $start['video_id'] );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		$page = self::page();
		if ( is_string( $page ) ) {
			return Publish_Result::failed( $page, false );
		}

		$res = ( new Meta_Client( $page['access_token'] ) )->video_status( $reference );
		if ( is_wp_error( $res ) ) {
			return Publish_Result::failed( $res->get_error_message() );
		}

		return self::result_from_status( $reference, $res );
	}

	/**
	 * Map a Graph video status to a result.
	 *
	 * @param string               $video_id Video ID.
	 * @param array<string, mixed> $res      Graph response with status and permalink_url.
	 */
	public static function result_from_status( string $video_id, array $res ): Publish_Result {
		$status  = (array) ( $res['status'] ?? array() );
		$publish = (array) ( $status['publishing_phase'] ?? array() );

		$error = self::status_error( $status );
		if ( '' !== $error ) {
			return Publish_Result::failed( $error, false );
		}

		if ( 'complete' === ( $publish['status'] ?? '' ) ) {
			$draft = 'draft' === strtolower( (string) ( $publish['publish_status'] ?? '' ) );
			return Publish_Result::published(
				$video_id,
				self::permalink( $video_id, (string) ( $res['permalink_url'] ?? '' ) ),
				$draft ? __( 'Saved as a draft Reel: the package privacy is not public.', '4wp-social' ) : ''
			);
		}

		return Publish_Result::processing( $video_id, (string) ( $status['video_status'] ?? '' ) );
	}

	/**
	 * Package privacy → video_state: only public packages are published.
	 *
	 * @param string $privacy Package privacy.
	 */
	public static function video_state( string $privacy ): string {
		return in_array( $privacy, array( 'private', 'unlisted' ), true ) ? 'DRAFT' : 'PUBLISHED';
	}

	/**
	 * Caption: same rules as TikTok (description or title, tags as hashtags, 2200 characters).
	 *
	 * @param Media_Package $package Package.
	 */
	public static function caption( Media_Package $package ): string {
		return TikTok_Adapter::caption( $package );
	}

	/**
	 * Absolute Reel URL.
	 *
	 * @param string $video_id  Video ID.
	 * @param string $permalink permalink_url from Graph (often relative).
	 */
	public static function permalink( string $video_id, string $permalink ): string {
		if ( '' === $permalink ) {
			return 'https://www.facebook.com/reel/' . rawurlencode( $video_id );
		}

		return 0 === strpos( $permalink, '/' ) ? 'https://www.facebook.com' . $permalink : $permalink;
	}

	/**
	 * First error message in any phase, empty if none.
	 *
	 * @param array<string, mixed> $status Graph status object.
	 */
	private static function status_error( array $status ): string {
		$failed = 'error' === ( $status['video_status'] ?? '' );
		foreach ( array( 'uploading_phase', 'processing_phase', 'publishing_phase' ) as $phase ) {
			$item = (array) ( $status[ $phase ] ?? array() );
			if ( 'error' !== ( $item['status'] ?? '' ) ) {
				continue;
			}
			$errors = (array) ( $item['errors'] ?? array() );
			$first  = isset( $errors[0] ) ? (array) $errors[0] : array();
			if ( ! empty( $first['message'] ) ) {
				return (string) $first['message'];
			}
			$failed = true;
		}

		return $failed ? __( 'Facebook could not process the Reel.', '4wp-social' ) : '';
	}

	/**
	 * The Page to post to: the first one the connection manages (filter forwp_social_meta_page_id picks another).
	 *
	 * @return array{id: string, name: string, access_token: string, ig_id: string, ig_username: string}|string Page or error text.
	 */
	public static function page() {
		$token = OAuth_Controller::instance()->get_access_token( Meta_OAuth_Provider::SLUG );
		if ( is_wp_error( $token ) ) {
			return $token->get_error_message();
		}

		$pages = Meta_OAuth_Provider::assets( $token );

		/**
		 * Page ID to publish Reels to; empty = the first connected Page.
		 *
		 * @param string $page_id Page ID.
		 * @param array  $pages   Connected Pages.
		 */
		$wanted = (string) apply_filters( 'forwp_social_meta_page_id', '', $pages );
		foreach ( $pages as $page ) {
			if ( ( '' === $wanted || $wanted === $page['id'] ) && '' !== $page['access_token'] ) {
				return $page;
			}
		}

		return __( 'The Meta connection has no Facebook Page. Reconnect and select the Page.', '4wp-social' );
	}
}
