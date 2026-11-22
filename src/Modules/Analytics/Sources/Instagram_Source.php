<?php
/**
 * Instagram: account totals, recent media and account insights (Graph API).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Auth\Meta_OAuth_Provider;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Posting\Adapters\Facebook_Reels_Adapter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The Instagram account linked to the Page of the Meta connection. Insights need `instagram_manage_insights`.
 */
final class Instagram_Source implements Analytics_Source_Interface {

	private const INSIGHTS = 'reach,views,accounts_engaged,total_interactions';

	/**
	 * Per-post insights. Reels also have the average watch time.
	 */
	private const MEDIA_INSIGHTS = 'views,reach,saved,shares,total_interactions';
	private const REELS_EXTRA    = 'ig_reels_avg_watch_time';
	private const MEDIA_LIMIT    = 50;
	private const DAYS           = 28;

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
		return __( 'Followers, 28-day account insights, reach by day and the last 50 posts with their own insights. Insights need the instagram_manage_insights permission (to confirm).', '4wp-social' );
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

		$since = time() - self::DAYS * DAY_IN_SECONDS;

		return array(
			'account'  => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $ig, array( 'fields' => 'id,username,followers_count,follows_count,media_count' ), $token ) ),
			'media'    => Analytics::part( $this->media( $ig, $token ) ),
			'insights' => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$ig . '/insights',
					array(
						'metric'      => self::INSIGHTS,
						'period'      => 'day',
						'metric_type' => 'total_value',
						'since'       => $since,
						'until'       => time(),
					),
					$token
				)
			),
			// Daily series: reach by day (time_series) for the chart.
			'daily'    => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$ig . '/insights',
					array(
						'metric' => 'reach',
						'period' => 'day',
						'since'  => $since,
						'until'  => time(),
					),
					$token
				)
			),
		);
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
