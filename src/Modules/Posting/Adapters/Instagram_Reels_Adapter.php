<?php
/**
 * Instagram Reels: container → resumable upload → poll → media_publish (ADR 0004).
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
 * Asynchronous: publish() returns processing with the container ID; check() publishes it once FINISHED.
 * Uses the Instagram account linked to the Meta connection's Page.
 */
final class Instagram_Reels_Adapter implements Platform_Adapter_Interface {

	public const SLUG = 'instagram.com';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Instagram Reels';
	}

	public function is_ready(): bool {
		return Credentials_Store::has( Meta_OAuth_Provider::SLUG ) && OAuth_Controller::instance()->is_connected( Meta_OAuth_Provider::SLUG );
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $package->has_video() ) {
			return Publish_Result::failed( __( 'No video file to upload.', '4wp-social' ), false );
		}
		if ( in_array( $package->privacy(), array( 'private', 'unlisted' ), true ) ) {
			return Publish_Result::failed( __( 'Instagram has no private or unlisted Reels: set the package privacy to public.', '4wp-social' ), false );
		}

		$page = self::account();
		if ( is_string( $page ) ) {
			return Publish_Result::failed( $page, false );
		}

		$client    = new Meta_Client( $page['access_token'] );
		$container = $client->ig_reel_container( $page['ig_id'], TikTok_Adapter::caption( $package ) );
		if ( is_wp_error( $container ) ) {
			return Publish_Result::failed( $container->get_error_message() );
		}

		$uploaded = $client->ig_upload( $container['id'], $package->video_path(), $container['uri'] );
		if ( is_wp_error( $uploaded ) ) {
			return Publish_Result::failed( $uploaded->get_error_message() );
		}

		return Publish_Result::processing( $container['id'] );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		$page = self::account();
		if ( is_string( $page ) ) {
			return Publish_Result::failed( $page, false );
		}

		$client = new Meta_Client( $page['access_token'] );
		$status = $client->ig_container_status( $reference );
		if ( is_wp_error( $status ) ) {
			return Publish_Result::failed( $status->get_error_message() );
		}

		$code = (string) ( $status['status_code'] ?? '' );
		$next = self::next_step( $code );
		if ( 'failed' === $next ) {
			/* translators: 1: status code, 2: details. */
			return Publish_Result::failed( trim( sprintf( __( 'Instagram processing %1$s. %2$s', '4wp-social' ), $code, (string) ( $status['status'] ?? '' ) ) ), false );
		}
		if ( 'wait' === $next ) {
			return Publish_Result::processing( $reference, $code );
		}

		$media_id = $client->ig_publish( $page['ig_id'], $reference );
		if ( is_wp_error( $media_id ) ) {
			return Publish_Result::failed( $media_id->get_error_message() );
		}

		$url = $client->ig_permalink( $media_id );

		return Publish_Result::published( $media_id, '' !== $url ? $url : 'https://www.instagram.com/' . rawurlencode( $page['ig_username'] ) . '/' );
	}

	/**
	 * What to do with a container status: publish, wait or failed.
	 *
	 * @param string $status_code Container status_code.
	 */
	public static function next_step( string $status_code ): string {
		switch ( $status_code ) {
			case 'FINISHED':
				return 'publish';
			case 'ERROR':
			case 'EXPIRED':
				return 'failed';
			default:
				return 'wait';
		}
	}

	/**
	 * Page with a linked Instagram account.
	 *
	 * @return array{id: string, name: string, access_token: string, ig_id: string, ig_username: string}|string Page or error text.
	 */
	private static function account() {
		$page = Facebook_Reels_Adapter::page();
		if ( is_string( $page ) ) {
			return $page;
		}
		if ( '' === $page['ig_id'] ) {
			/* translators: %s: Facebook Page name. */
			return sprintf( __( 'No Instagram professional account is linked to the Facebook Page %s. Link it in the Page settings, then Reconnect Meta.', '4wp-social' ), $page['name'] );
		}

		return $page;
	}
}
