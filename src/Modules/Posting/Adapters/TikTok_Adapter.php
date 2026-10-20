<?php
/**
 * TikTok: creator info → init → chunked upload → poll status.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Api\TikTok_Client;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Platform_Adapter_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Asynchronous: publish() returns processing with publish_id, check() polls until PUBLISH_COMPLETE.
 * Unaudited apps can only post SELF_ONLY (private) until TikTok approves the app.
 */
final class TikTok_Adapter implements Platform_Adapter_Interface {

	public const SLUG = 'tiktok.com';

	/**
	 * Caption limit (UTF-16 units; characters are a safe approximation).
	 */
	private const CAPTION_MAX = 2200;

	/**
	 * Package privacy → TikTok privacy_level.
	 */
	private const PRIVACY_MAP = array(
		'public'    => 'PUBLIC_TO_EVERYONE',
		'followers' => 'FOLLOWER_OF_CREATOR',
		'friends'   => 'MUTUAL_FOLLOW_FRIENDS',
		'unlisted'  => 'SELF_ONLY',
		'private'   => 'SELF_ONLY',
	);

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'TikTok';
	}

	public function is_ready(): bool {
		return Credentials_Store::has( self::SLUG ) && OAuth_Controller::instance()->is_connected( self::SLUG );
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $package->has_video() ) {
			return Publish_Result::failed( __( 'No video file to upload.', '4wp-social' ), false );
		}

		$token = OAuth_Controller::instance()->get_access_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			return Publish_Result::failed( $token->get_error_message(), false );
		}

		$client  = new TikTok_Client( $token );
		$creator = $client->creator_info();
		if ( is_wp_error( $creator ) ) {
			return Publish_Result::failed( $creator->get_error_message() );
		}

		$options = array_map( 'strval', (array) ( $creator['privacy_level_options'] ?? array() ) );
		$privacy = self::resolve_privacy( $package->privacy(), $options );
		if ( '' === $privacy ) {
			return Publish_Result::failed( __( 'TikTok returned no privacy options for this account.', '4wp-social' ), false );
		}

		$entry     = $package->entry();
		$post_info = array(
			'title'           => self::caption( $package ),
			'privacy_level'   => $privacy,
			'disable_comment' => ! empty( $entry['disable_comment'] ),
			'disable_duet'    => ! empty( $entry['disable_duet'] ),
			'disable_stitch'  => ! empty( $entry['disable_stitch'] ),
		);
		if ( $package->cover_ms() > 0 ) {
			$post_info['video_cover_timestamp_ms'] = $package->cover_ms();
		}

		$init = $client->init_upload( $post_info, (int) filesize( $package->video_path() ) );
		if ( is_wp_error( $init ) ) {
			$reason = (string) ( $init->get_error_data()['reason'] ?? '' );
			return Publish_Result::failed( $init->get_error_message(), 'rate_limit_exceeded' === $reason );
		}

		$publish_id = (string) ( $init['publish_id'] ?? '' );
		$upload_url = (string) ( $init['upload_url'] ?? '' );
		if ( '' === $publish_id || '' === $upload_url ) {
			return Publish_Result::failed( __( 'TikTok did not return an upload URL.', '4wp-social' ) );
		}

		$uploaded = $client->upload_file( $upload_url, $package->video_path() );
		if ( is_wp_error( $uploaded ) ) {
			return Publish_Result::failed( $uploaded->get_error_message() );
		}

		$note = '';
		if ( ( self::PRIVACY_MAP[ $package->privacy() ] ?? 'PUBLIC_TO_EVERYONE' ) !== $privacy ) {
			/* translators: %s: TikTok privacy level. */
			$note = sprintf( __( 'Posted as %s: the app is not audited or the account does not allow the requested privacy.', '4wp-social' ), $privacy );
		}

		return Publish_Result::processing( $publish_id, $note );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		$token = OAuth_Controller::instance()->get_access_token( self::SLUG );
		if ( is_wp_error( $token ) ) {
			return Publish_Result::failed( $token->get_error_message(), false );
		}

		$status = ( new TikTok_Client( $token ) )->fetch_status( $reference );
		if ( is_wp_error( $status ) ) {
			return Publish_Result::failed( $status->get_error_message() );
		}

		switch ( (string) ( $status['status'] ?? '' ) ) {
			case 'PUBLISH_COMPLETE':
				// TikTok spells the field "publicaly_available_post_id"; it is empty for private posts.
				$ids = (array) ( $status['publicaly_available_post_id'] ?? array() );
				$id  = isset( $ids[0] ) ? (string) $ids[0] : '';
				if ( '' === $id ) {
					return Publish_Result::published( $reference, '', __( 'Published privately: TikTok gives no public post id.', '4wp-social' ) );
				}
				return Publish_Result::published( $id, self::video_url( $id ) );

			case 'FAILED':
				return Publish_Result::failed( (string) ( $status['fail_reason'] ?? __( 'TikTok processing failed.', '4wp-social' ) ), false );

			default:
				return Publish_Result::processing( $reference, (string) ( $status['status'] ?? '' ) );
		}
	}

	/**
	 * Requested privacy if the account allows it, else SELF_ONLY, else the first allowed option.
	 *
	 * @param string             $requested Package privacy (public|followers|friends|unlisted|private).
	 * @param array<int, string> $options   privacy_level_options from creator info.
	 */
	public static function resolve_privacy( string $requested, array $options ): string {
		$wanted = self::PRIVACY_MAP[ $requested ] ?? 'PUBLIC_TO_EVERYONE';
		if ( in_array( $wanted, $options, true ) ) {
			return $wanted;
		}
		if ( in_array( 'SELF_ONLY', $options, true ) ) {
			return 'SELF_ONLY';
		}

		return isset( $options[0] ) ? (string) $options[0] : '';
	}

	/**
	 * Caption = description (or title), with tags as hashtags, cut to the TikTok limit.
	 *
	 * @param Media_Package $package Package.
	 */
	public static function caption( Media_Package $package ): string {
		$text = '' !== trim( $package->description() ) ? $package->description() : $package->title();

		$hashtags = array();
		foreach ( $package->tags() as $tag ) {
			$tag = preg_replace( '/[^\p{L}\p{N}_]/u', '', $tag );
			if ( '' !== $tag && false === stripos( $text, '#' . $tag ) ) {
				$hashtags[] = '#' . $tag;
			}
		}
		if ( $hashtags ) {
			$text = trim( $text ) . "\n\n" . implode( ' ', $hashtags );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::CAPTION_MAX ) : substr( $text, 0, self::CAPTION_MAX );
	}

	/**
	 * Public URL. Uses the connected @username when known.
	 *
	 * @param string $id Post id.
	 */
	private static function video_url( string $id ): string {
		$account = ltrim( OAuth_Controller::instance()->account_label( self::SLUG ), '@' );
		if ( '' !== $account && false === strpos( $account, ' ' ) ) {
			return 'https://www.tiktok.com/@' . rawurlencode( $account ) . '/video/' . rawurlencode( $id );
		}

		return 'https://www.tiktok.com/video/' . rawurlencode( $id );
	}
}
