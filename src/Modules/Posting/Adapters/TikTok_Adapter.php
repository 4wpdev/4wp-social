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
 * Posts only with the author's explicit choices from the post box (privacy, interactions,
 * commercial content disclosure, consent) — TikTok Content Sharing Guidelines.
 * Unaudited apps can post only to a private account.
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
	/**
	 * TikTok privacy levels.
	 */
	public const PRIVACY_LEVELS = array( 'PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY' );

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

		$choice = $package->tiktok();
		$error  = self::validate_choice( $choice, $creator );
		if ( '' !== $error ) {
			return Publish_Result::failed( $error, false );
		}

		$title     = trim( (string) $choice['title'] );
		$post_info = array(
			'title'                => '' !== $title ? $title : self::caption( $package ),
			'privacy_level'        => (string) $choice['privacy_level'],
			'disable_comment'      => empty( $choice['allow_comment'] ) || ! empty( $creator['comment_disabled'] ),
			'disable_duet'         => empty( $choice['allow_duet'] ) || ! empty( $creator['duet_disabled'] ),
			'disable_stitch'       => empty( $choice['allow_stitch'] ) || ! empty( $creator['stitch_disabled'] ),
			'brand_content_toggle' => ! empty( $choice['brand_content'] ),
			'brand_organic_toggle' => ! empty( $choice['brand_organic'] ),
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

		return Publish_Result::processing( $publish_id, __( 'Sent to TikTok. Processing may take a few minutes before the video appears on the profile.', '4wp-social' ) );
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
	 * Author's TikTok choices from the post box. Empty without a privacy level or consent.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_options( array $input ): array {
		$privacy = strtoupper( sanitize_key( (string) ( $input['privacy_level'] ?? '' ) ) );
		if ( ! in_array( $privacy, self::PRIVACY_LEVELS, true ) || empty( $input['consent'] ) ) {
			return array();
		}
		$title = sanitize_textarea_field( (string) ( $input['title'] ?? '' ) );

		return array(
			'privacy_level' => $privacy,
			'title'         => function_exists( 'mb_substr' ) ? mb_substr( $title, 0, self::CAPTION_MAX ) : substr( $title, 0, self::CAPTION_MAX ),
			'allow_comment' => ! empty( $input['allow_comment'] ),
			'allow_duet'    => ! empty( $input['allow_duet'] ),
			'allow_stitch'  => ! empty( $input['allow_stitch'] ),
			'brand_organic' => ! empty( $input['commercial'] ) && ! empty( $input['brand_organic'] ),
			'brand_content' => ! empty( $input['commercial'] ) && ! empty( $input['brand_content'] ),
			'consent'       => true,
		);
	}

	/**
	 * Why the choices cannot be posted now ('' = ok).
	 *
	 * @param array<string, mixed> $choice  sanitize_options() result.
	 * @param array<string, mixed> $creator creator_info data.
	 */
	public static function validate_choice( array $choice, array $creator ): string {
		if ( empty( $choice['privacy_level'] ) || empty( $choice['consent'] ) ) {
			return __( 'TikTok settings were not chosen. Open the post, tick TikTok, choose who can view the video and publish from the post box.', '4wp-social' );
		}
		$options = array_map( 'strval', (array) ( $creator['privacy_level_options'] ?? array() ) );
		if ( ! in_array( (string) $choice['privacy_level'], $options, true ) ) {
			/* translators: %s: TikTok privacy level. */
			return sprintf( __( 'The TikTok account does not allow the privacy "%s" now. Choose another option in the post box.', '4wp-social' ), (string) $choice['privacy_level'] );
		}
		if ( ! empty( $choice['brand_content'] ) && 'SELF_ONLY' === $choice['privacy_level'] ) {
			return __( 'Branded content visibility cannot be set to private.', '4wp-social' );
		}

		return '';
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
