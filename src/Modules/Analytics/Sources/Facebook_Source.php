<?php
/**
 * Facebook: Page totals, Page insights by day (history from the API) and Reels (lifetime counters → daily snapshots).
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
 * Uses the Page token of the Meta connection. Insights need `read_insights`.
 */
final class Facebook_Source implements Analytics_Source_Interface, Analytics_Cumulative_Interface {

	/**
	 * Page insights by day: Graph metric => stored key. page_impressions* are deprecated by Meta.
	 */
	private const INSIGHTS    = array(
		'page_media_view'           => 'views',
		'page_post_engagements'     => 'engagements',
		'page_daily_follows_unique' => 'follows',
	);
	private const REELS_LIMIT = 50;
	private const CHUNK_DAYS  = 90; // Graph allows since/until up to 93 days apart.

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return Meta_OAuth_Provider::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Facebook', '4wp-social' );
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
		return __( 'Page totals, Page insights by day (views, engagements, new follows) and the last 50 Reels. Reels give lifetime counters only, so Reels per period start with the first collection. Insights need the read_insights permission in the Meta configuration.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$page = Facebook_Reels_Adapter::page();
		if ( is_string( $page ) ) {
			return new WP_Error( 'forwp_social_facebook_analytics', $page );
		}

		$token = $page['access_token'];
		$id    = $page['id'];

		return array(
			'page'  => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $id, array( 'fields' => 'id,name,link,fan_count,followers_count' ), $token ) ),
			'reels' => Analytics::part( $this->reels( $id, $token ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $raw fetch() answer.
	 */
	public function snapshot( array $raw ): ?array {
		$page  = is_array( $raw['page'] ?? null ) ? $raw['page'] : array();
		$reels = is_array( $raw['reels'] ?? null ) ? $raw['reels'] : array();
		if ( ! $page || isset( $page['error'] ) || isset( $reels['error'] ) ) {
			return null;
		}

		$out = array(
			'account' => array( 'followers' => (float) ( $page['followers_count'] ?? 0 ) ),
			'items'   => array(),
			'meta'    => array(),
		);
		foreach ( (array) ( $reels['data'] ?? array() ) as $reel ) {
			$reel = (array) $reel;
			$id   = (string) ( $reel['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$out['items'][ $id ] = array(
				'views'    => (float) ( $reel['views'] ?? 0 ),
				'likes'    => (float) ( $reel['likes'] ?? 0 ),
				'comments' => (float) ( $reel['comments'] ?? 0 ),
			);
			$link                = (string) ( $reel['permalink_url'] ?? '' );
			$out['meta'][ $id ]  = array(
				'title'        => trim( (string) strtok( html_entity_decode( (string) ( $reel['description'] ?? '' ), ENT_QUOTES, 'UTF-8' ), "\n" ) ),
				'url'          => '' !== $link && '/' === $link[0] ? 'https://www.facebook.com' . $link : $link,
				'thumbnail'    => (string) ( $reel['picture'] ?? '' ),
				'duration'     => (int) round( (float) ( $reel['length'] ?? 0 ) ),
				'published_at' => ! empty( $reel['created_time'] ) ? gmdate( 'c', (int) strtotime( (string) $reel['created_time'] ) ) : '',
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
			return new WP_Error( 'forwp_social_facebook_analytics', $page );
		}

		$from = (int) strtotime( $start . ' 00:00:00 UTC' );
		$last = (int) strtotime( $end . ' 00:00:00 UTC' ) + DAY_IN_SECONDS;
		$out  = array();
		while ( $from < $last ) {
			$to  = min( $last, $from + self::CHUNK_DAYS * DAY_IN_SECONDS );
			$res = Meta_OAuth_Provider::graph(
				'GET',
				$page['id'] . '/insights',
				array(
					'metric' => implode( ',', array_keys( self::INSIGHTS ) ),
					'period' => 'day',
					'since'  => $from,
					'until'  => $to,
				),
				$page['access_token']
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			foreach ( self::daily_rows( (array) $res ) as $day => $row ) {
				$out[ $day ] = $row + ( $out[ $day ] ?? array() );
			}
			$from = $to;
		}

		return array_filter(
			$out,
			static function ( $day ) use ( $start, $end ): bool {
				return $day >= $start && $day <= $end;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Graph insights answer → day => key => value. `end_time` is the end of the day, so the day is one earlier.
	 *
	 * @param array<string, mixed> $res Graph `/insights` answer.
	 * @return array<string, array<string, float>>
	 */
	public static function daily_rows( array $res ): array {
		$out = array();
		foreach ( (array) ( $res['data'] ?? array() ) as $metric ) {
			$key = self::INSIGHTS[ (string) ( $metric['name'] ?? '' ) ] ?? '';
			if ( '' === $key ) {
				continue;
			}
			foreach ( (array) ( $metric['values'] ?? array() ) as $value ) {
				$time = strtotime( (string) ( $value['end_time'] ?? '' ) );
				if ( false === $time || ! is_numeric( $value['value'] ?? null ) ) {
					continue;
				}
				$out[ gmdate( 'Y-m-d', $time - DAY_IN_SECONDS ) ][ $key ] = (float) $value['value'];
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
			'views'       => array(
				'label' => __( 'Views', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
			'engagements' => array(
				'label' => __( 'Engagements', '4wp-social' ),
				'agg'   => 'sum',
			),
			'follows'     => array(
				'label' => __( 'New follows', '4wp-social' ),
				'agg'   => 'sum',
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
	 * @param int    $limit Max Reels.
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
		);
	}

	/**
	 * Last Reels with public counters; per-Reel insights when the token allows them.
	 *
	 * @param string $id    Page id.
	 * @param string $token Page token.
	 * @return array<string, mixed>|WP_Error
	 */
	private function reels( string $id, string $token ) {
		$list = Meta_OAuth_Provider::graph(
			'GET',
			$id . '/video_reels',
			array(
				'fields' => 'id,description,created_time,permalink_url,length,picture,views,likes.summary(true).limit(0),comments.summary(true).limit(0)',
				'limit'  => self::REELS_LIMIT,
			),
			$token
		);
		if ( is_wp_error( $list ) ) {
			return $list;
		}

		$data  = array();
		$error = '';
		foreach ( (array) ( $list['data'] ?? array() ) as $item ) {
			$item             = (array) $item;
			$item['likes']    = (int) ( $item['likes']['summary']['total_count'] ?? 0 );
			$item['comments'] = (int) ( $item['comments']['summary']['total_count'] ?? 0 );
			$item['insights'] = array();

			// One refusal (missing read_insights) means all will be refused: stop asking.
			if ( '' === $error ) {
				$res = Meta_OAuth_Provider::graph( 'GET', (string) ( $item['id'] ?? '' ) . '/video_insights', array(), $token );
				if ( is_wp_error( $res ) ) {
					$error = $res->get_error_message();
				} else {
					foreach ( (array) ( $res['data'] ?? array() ) as $metric ) {
						$item['insights'][ (string) ( $metric['name'] ?? '' ) ] = $metric['values'][0]['value'] ?? null;
					}
				}
			}
			$data[] = $item;
		}

		return array(
			'data'           => $data,
			'insights_error' => $error,
		);
	}
}
