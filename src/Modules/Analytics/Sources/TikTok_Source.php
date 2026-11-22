<?php
/**
 * TikTok: account stats (user/info) and recent videos with counters (video/list).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Api\Http;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Auth\TikTok_OAuth_Provider;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Needs `user.info.profile`, `user.info.stats` and `video.list` (Display API in the TikTok app), then Reconnect.
 */
final class TikTok_Source implements Analytics_Source_Interface {

	private const API          = 'https://open.tiktokapis.com/v2';
	private const USER_FIELDS  = 'open_id,display_name,username,avatar_url,profile_deep_link,follower_count,following_count,likes_count,video_count';
	private const VIDEO_FIELDS = 'id,title,video_description,create_time,share_url,duration,cover_image_url,view_count,like_count,comment_count,share_count';
	private const VIDEO_LIMIT  = 100;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return TikTok_OAuth_Provider::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'TikTok', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): string {
		return OAuth_Controller::instance()->is_connected( $this->slug() ) ? Analytics::READY : Analytics::NOT_CONNECTED;
	}

	/**
	 * {@inheritDoc}
	 */
	public function note(): string {
		return __( 'Account totals and the last 100 videos with views, likes, comments and shares. Needs the user.info.profile, user.info.stats and video.list scopes (Display API) in the TikTok app, then Reconnect.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$token = OAuth_Controller::instance()->get_access_token( $this->slug() );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$headers = array( 'Authorization' => 'Bearer ' . $token );

		return array(
			'user'   => Analytics::part( $this->unwrap( Http::request( 'GET', self::API . '/user/info/?fields=' . self::USER_FIELDS, array( 'headers' => $headers ) ) ) ),
			'videos' => Analytics::part( $this->videos( $headers ) ),
		);
	}

	/**
	 * Videos, newest first, 20 per page up to VIDEO_LIMIT.
	 *
	 * @param array<string, string> $headers Auth headers.
	 * @return array<string, mixed>|WP_Error
	 */
	private function videos( array $headers ) {
		$videos = array();
		$cursor = 0;
		do {
			$body = array( 'max_count' => 20 ) + ( $cursor ? array( 'cursor' => $cursor ) : array() );
			$page = $this->unwrap( Http::json( 'POST', self::API . '/video/list/?fields=' . self::VIDEO_FIELDS, $body, $headers ) );
			if ( is_wp_error( $page ) ) {
				return $videos ? array( 'videos' => $videos, 'error_more' => $page->get_error_message() ) : $page;
			}
			$videos = array_merge( $videos, (array) ( $page['videos'] ?? array() ) );
			$cursor = (int) ( $page['cursor'] ?? 0 );
		} while ( ! empty( $page['has_more'] ) && $cursor && count( $videos ) < self::VIDEO_LIMIT );

		return array( 'videos' => $videos );
	}

	/**
	 * Unwrap TikTok `{data, error}`.
	 *
	 * @param array<string, mixed>|WP_Error $res Response.
	 * @return array<string, mixed>|WP_Error
	 */
	private function unwrap( $res ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (string) ( $res['json']['error']['code'] ?? '' );
		if ( 200 !== $res['code'] || ( '' !== $code && 'ok' !== $code ) ) {
			return new WP_Error( 'forwp_social_tiktok_analytics', Http::error_message( $res, __( 'TikTok request failed.', '4wp-social' ) ) );
		}

		return (array) ( $res['json']['data'] ?? array() );
	}
}
