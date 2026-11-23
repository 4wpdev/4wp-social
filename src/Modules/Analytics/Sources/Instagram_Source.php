<?php
/**
 * Instagram: account totals, reach by day (history from the API) and recent posts (lifetime counters → daily snapshots).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Auth\Meta_OAuth_Provider;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Analytics_Cumulative_Interface;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Metrics_Store;
use ForWP\Social\Modules\Posting\Adapters\Facebook_Reels_Adapter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The Instagram account linked to the Page of the Meta connection. Insights need `instagram_manage_insights`.
 */
final class Instagram_Source implements Analytics_Source_Interface, Analytics_Cumulative_Interface {

	/**
	 * Only reach has a daily series (time_series); the other account metrics are period totals.
	 */
	private const DAILY_METRIC = 'reach';
	private const CHUNK_DAYS   = 30; // Graph allows since/until up to 30 days apart.

	/**
	 * Per-post insights. Reels also have the average watch time.
	 */
	private const MEDIA_INSIGHTS = 'views,reach,saved,shares,total_interactions';
	private const REELS_EXTRA    = 'ig_reels_avg_watch_time';
	private const MEDIA_LIMIT    = 50;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'instagram.com';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Instagram', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): string {
		return OAuth_Controller::instance()->is_connected( Meta_OAuth_Provider::SLUG ) ? Analytics::READY : Analytics::NOT_CONNECTED;
	}

	/**
	 * {@inheritDoc}
	 */
	public function note(): string {
		return __( 'Account totals, reach by day and the last 50 posts. Posts give lifetime counters only, so posts per period start with the first collection. Insights need the instagram_manage_insights permission.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$page = Facebook_Reels_Adapter::page();
		if ( is_string( $page ) ) {
			return new WP_Error( 'forwp_social_instagram_analytics', $page );
		}
		if ( '' === $page['ig_id'] ) {
			return new WP_Error( 'forwp_social_instagram_analytics', __( 'No Instagram professional account is linked to the Facebook Page.', '4wp-social' ) );
		}

		$token = $page['access_token'];
		$ig    = $page['ig_id'];

		return array(
			'account' => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $ig, array( 'fields' => 'id,username,followers_count,follows_count,media_count' ), $token ) ),
			'media'   => Analytics::part( $this->media( $ig, $token ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $raw fetch() answer.
	 */
	public function snapshot( array $raw ): ?array {
		$account = is_array( $raw['account'] ?? null ) ? $raw['account'] : array();
		$media   = is_array( $raw['media'] ?? null ) ? $raw['media'] : array();
		if ( ! $account || isset( $account['error'] ) || isset( $media['error'] ) ) {
			return null;
		}

		$out = array(
			'account' => array( 'followers' => (float) ( $account['followers_count'] ?? 0 ) ),
			'items'   => array(),
			'meta'    => array(),
		);
		foreach ( (array) ( $media['data'] ?? array() ) as $post ) {
			$post = (array) $post;
			$id   = (string) ( $post['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$ins                 = (array) ( $post['insights'] ?? array() );
			$out['items'][ $id ] = array(
				'views'    => (float) ( $ins['views'] ?? 0 ),
				'reach'    => (float) ( $ins['reach'] ?? 0 ),
				'likes'    => (float) ( $post['like_count'] ?? 0 ),
				'comments' => (float) ( $post['comments_count'] ?? 0 ),
				'saved'    => (float) ( $ins['saved'] ?? 0 ),
				'shares'   => (float) ( $ins['shares'] ?? 0 ),
			);
			$out['meta'][ $id ]  = array(
				'title'        => trim( (string) strtok( (string) ( $post['caption'] ?? '' ), "\n" ) ),
				'url'          => (string) ( $post['permalink'] ?? '' ),
				'thumbnail'    => (string) ( $post['thumbnail_url'] ?? ( $post['media_url'] ?? '' ) ),
				'duration'     => 0,
				'published_at' => ! empty( $post['timestamp'] ) ? gmdate( 'c', (int) strtotime( (string) $post['timestamp'] ) ) : '',
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
		$page = Facebook_Reels_Adapter::page();
		if ( is_string( $page ) ) {
			return new WP_Error( 'forwp_social_instagram_analytics', $page );
		}
		if ( '' === $page['ig_id'] ) {
			return new WP_Error( 'forwp_social_instagram_analytics', __( 'No Instagram professional account is linked to the Facebook Page.', '4wp-social' ) );
		}

		$from = (int) strtotime( $start . ' 00:00:00 UTC' );
		$last = (int) strtotime( $end . ' 00:00:00 UTC' ) + DAY_IN_SECONDS;
		$out  = array();
		while ( $from < $last ) {
			$to  = min( $last, $from + self::CHUNK_DAYS * DAY_IN_SECONDS );
			$res = Meta_OAuth_Provider::graph(
				'GET',
				$page['ig_id'] . '/insights',
				array(
					'metric' => self::DAILY_METRIC,
					'period' => 'day',
					'since'  => $from,
					'until'  => $to,
				),
				$page['access_token']
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$out  = self::daily_rows( (array) $res ) + $out;
			$from = $to;
		}
		ksort( $out );

		return array_filter(
			$out,
			static function ( $day ) use ( $start, $end ): bool {
				return $day >= $start && $day <= $end;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Graph reach time series → day => [ reach ]. `end_time` is the end of the day, so the day is one earlier.
	 *
	 * @param array<string, mixed> $res Graph `/insights` answer.
	 * @return array<string, array<string, float>>
	 */
	public static function daily_rows( array $res ): array {
		$out = array();
		foreach ( (array) ( $res['data'] ?? array() ) as $metric ) {
			if ( self::DAILY_METRIC !== ( $metric['name'] ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $metric['values'] ?? array() ) as $value ) {
				$time = strtotime( (string) ( $value['end_time'] ?? '' ) );
				if ( false !== $time && is_numeric( $value['value'] ?? null ) ) {
					$out[ gmdate( 'Y-m-d', $time - DAY_IN_SECONDS ) ] = array( 'reach' => (float) $value['value'] );
				}
			}
		}
		ksort( $out );

		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function metrics(): array {
		return array(
			'reach' => array(
				'label' => __( 'Reach', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function history_days(): int {
		return 365;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @param int    $limit Max posts.
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
		$out = array();
		foreach ( array(
			'views'    => __( 'Views', '4wp-social' ),
			'reach'    => __( 'Reach', '4wp-social' ),
			'likes'    => __( 'Likes', '4wp-social' ),
			'comments' => __( 'Comments', '4wp-social' ),
			'saved'    => __( 'Saved', '4wp-social' ),
			'shares'   => __( 'Shares', '4wp-social' ),
		) as $key => $label ) {
			$out[ $key ] = array(
				'label'  => $label,
				'format' => 'num',
			);
		}

		return $out;
	}

	/**
	 * Last posts with their own insights (one request per post; a refused metric leaves the post without numbers).
	 *
	 * @param string $ig    Instagram user id.
	 * @param string $token Page token.
	 * @return array<string, mixed>|WP_Error
	 */
	private function media( string $ig, string $token ) {
		$list = Meta_OAuth_Provider::graph(
			'GET',
			$ig . '/media',
			array(
				'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count,thumbnail_url,media_url',
				'limit'  => self::MEDIA_LIMIT,
			),
			$token
		);
		if ( is_wp_error( $list ) ) {
			return $list;
		}

		$data = array();
		foreach ( (array) ( $list['data'] ?? array() ) as $item ) {
			$item    = (array) $item;
			$reels   = 'REELS' === ( $item['media_product_type'] ?? '' );
			$metrics = self::MEDIA_INSIGHTS . ( $reels ? ',' . self::REELS_EXTRA : '' );
			$res     = Meta_OAuth_Provider::graph( 'GET', (string) ( $item['id'] ?? '' ) . '/insights', array( 'metric' => $metrics ), $token );

			$item['insights'] = array();
			if ( is_wp_error( $res ) ) {
				$item['insights_error'] = $res->get_error_message();
			} else {
				foreach ( (array) ( $res['data'] ?? array() ) as $metric ) {
					$item['insights'][ (string) ( $metric['name'] ?? '' ) ] = $metric['values'][0]['value'] ?? ( $metric['total_value']['value'] ?? null );
				}
			}
			$data[] = $item;
		}

		return array( 'data' => $data );
	}
}
