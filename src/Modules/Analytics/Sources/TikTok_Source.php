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
 * Needs `user.info.stats` and `video.list` (enable them in the TikTok app, see forwp_social_oauth_scopes).
 */
final class TikTok_Source implements Analytics_Source_Interface {

	private const API          = 'https://open.tiktokapis.com/v2';
	private const USER_FIELDS  = 'open_id,display_name,follower_count,following_count,likes_count,video_count';
	private const VIDEO_FIELDS = 'id,title,create_time,share_url,duration,view_count,like_count,comment_count,share_count';

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
		return __( 'Account totals and the last 20 videos with views, likes, comments and shares. Needs the user.info.stats and video.list scopes approved in the TikTok app, then Reconnect.', '4wp-social' );
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
			'videos' => Analytics::part( $this->unwrap( Http::json( 'POST', self::API . '/video/list/?fields=' . self::VIDEO_FIELDS, array( 'max_count' => 20 ), $headers ) ) ),
		);
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
