<?php
/**
 * TikTok: account stats (user/info) and recent videos with counters (video/list).
 * The API gives lifetime counters only: daily changes come from daily snapshots (Metrics_Store).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Api\Http;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Auth\TikTok_OAuth_Provider;
use ForWP\Social\Contracts\Analytics_Cumulative_Interface;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Metrics_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Needs `user.info.profile`, `user.info.stats` and `video.list` (Display API in the TikTok app), then Reconnect.
 */
final class TikTok_Source implements Analytics_Source_Interface, Analytics_Cumulative_Interface {

	private const API          = 'https://open.tiktokapis.com/v2';
	private const USER_FIELDS  = 'open_id,display_name,username,avatar_url,profile_deep_link,follower_count,following_count,likes_count,video_count';
	private const VIDEO_FIELDS = 'id,title,video_description,create_time,share_url,duration,cover_image_url,view_count,like_count,comment_count,share_count';
	private const VIDEO_LIMIT  = 100;
	private const SCOPES       = array( 'user.info.profile', 'user.info.stats', 'video.list' );

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
		return __( 'Account totals and the last 100 videos. TikTok gives lifetime counters only, so daily numbers start with the first collection. Needs the user.info.profile, user.info.stats and video.list scopes (Display API) in the TikTok app; turn them on in Platforms → TikTok → step 2, then Reconnect.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$missing = $this->missing_scopes();
		if ( $missing ) {
			return new WP_Error(
				'forwp_social_tiktok_scopes',
				/* translators: %s: comma-separated TikTok scopes. */
				sprintf( __( 'TikTok connection lacks the scopes: %s. Add them to the TikTok app, turn them on in Platforms → TikTok → step 2, then Reconnect.', '4wp-social' ), implode( ', ', $missing ) )
			);
		}

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
	 * Analytics scopes the connected token was not granted.
	 *
	 * @return array<string>
	 */
	private function missing_scopes(): array {
		$granted = (string) OAuth_Controller::instance()->connection_details( $this->slug() )['scope'];
		if ( '' === $granted ) {
			return array(); // Older tokens without the stored scope: let the API answer.
		}

		return array_values( array_diff( self::SCOPES, array_map( 'trim', explode( ',', $granted ) ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $raw fetch() answer.
	 */
	public function snapshot( array $raw ): ?array {
		$user   = is_array( $raw['user'] ?? null ) ? $raw['user'] : array();
		$videos = is_array( $raw['videos'] ?? null ) ? $raw['videos'] : array();
		if ( isset( $user['error'] ) || isset( $videos['error'] ) || ! $user ) {
			return null;
		}
		$user = isset( $user['user'] ) ? (array) $user['user'] : $user;

		$out = array(
			'account' => array(
				'followers' => (float) ( $user['follower_count'] ?? 0 ),
				'videos'    => (float) ( $user['video_count'] ?? 0 ),
			),
			'items'   => array(),
			'meta'    => array(),
		);
		foreach ( (array) ( $videos['videos'] ?? array() ) as $video ) {
			$video = (array) $video;
			$id    = (string) ( $video['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$out['items'][ $id ] = array(
				'views'    => (float) ( $video['view_count'] ?? 0 ),
				'likes'    => (float) ( $video['like_count'] ?? 0 ),
				'comments' => (float) ( $video['comment_count'] ?? 0 ),
				'shares'   => (float) ( $video['share_count'] ?? 0 ),
			);
			$title               = trim( (string) ( $video['title'] ?? '' ) );
			$title               = '' !== $title ? $title : trim( (string) strtok( (string) ( $video['video_description'] ?? '' ), "\n" ) );
			$out['meta'][ $id ]  = array(
				'title'        => $title,
				'url'          => (string) ( $video['share_url'] ?? '' ),
				'thumbnail'    => (string) ( $video['cover_image_url'] ?? '' ),
				'duration'     => (int) ( $video['duration'] ?? 0 ),
				'published_at' => ! empty( $video['create_time'] ) ? gmdate( 'c', (int) $video['create_time'] ) : '',
			);
		}

		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 */
	public function daily( string $start, string $end ) {
		return Metrics_Store::rows( $this->slug(), $start, $end );
	}

	/**
	 * {@inheritDoc}
	 */
	public function metrics(): array {
		return array(
			'views'     => array(
				'label' => __( 'Views', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
			'likes'     => array(
				'label' => __( 'Likes', '4wp-social' ),
				'agg'   => 'sum',
			),
			'comments'  => array(
				'label' => __( 'Comments', '4wp-social' ),
				'agg'   => 'sum',
			),
			'shares'    => array(
				'label' => __( 'Shares', '4wp-social' ),
				'agg'   => 'sum',
			),
			'followers' => array(
				'label' => __( 'Net followers', '4wp-social' ),
				'agg'   => 'sum',
			),
			'videos'    => array(
				'label' => __( 'New videos', '4wp-social' ),
				'agg'   => 'sum',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function history_days(): int {
		return 1;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @param int    $limit Max videos.
	 */
	public function items( string $start, string $end, int $limit ) {
		$items = Metrics_Store::cumulative_items( $this->slug(), $start, $end );
		usort(
			$items,
			static function ( $a, $b ): int {
				return ( $b['views'] ?? 0 ) <=> ( $a['views'] ?? 0 );
			}
		);

		return array_slice( $items, 0, max( 1, $limit ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function item_columns(): array {
		return array(
			'views'    => array(
				'label'  => __( 'Views', '4wp-social' ),
				'format' => 'num',
			),
			'likes'    => array(
				'label'  => __( 'Likes', '4wp-social' ),
				'format' => 'num',
			),
			'comments' => array(
				'label'  => __( 'Comments', '4wp-social' ),
				'format' => 'num',
			),
			'shares'   => array(
				'label'  => __( 'Shares', '4wp-social' ),
				'format' => 'num',
			),
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
				return $videos ? array(
					'videos'     => $videos,
					'error_more' => $page->get_error_message(),
				) : $page;
			}
			$videos = array_merge( $videos, (array) ( $page['videos'] ?? array() ) );
			$cursor = (int) ( $page['cursor'] ?? 0 );
			$total  = count( $videos );
		} while ( ! empty( $page['has_more'] ) && $cursor && $total < self::VIDEO_LIMIT );

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
