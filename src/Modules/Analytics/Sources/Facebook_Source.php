<?php
/**
 * Facebook: Page totals, recent Reels and Page insights (Graph API).
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
 * Uses the Page token of the Meta connection. Insights need `read_insights`.
 */
final class Facebook_Source implements Analytics_Source_Interface {

	/**
	 * Page insights by day (sum = 28-day total). page_impressions* are deprecated by Meta.
	 */
	private const INSIGHTS    = 'page_media_view,page_post_engagements,page_follows,page_daily_follows_unique';
	private const REELS_LIMIT = 50;
	private const DAYS        = 28;

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
		return __( 'Page followers, 28-day Page insights, views by day and the last 50 Reels (views, likes, comments; reach and watch time with insights). Insights need the read_insights permission in the Meta configuration.', '4wp-social' );
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
		$range = array(
			'period' => 'day',
			'since'  => time() - self::DAYS * DAY_IN_SECONDS,
			'until'  => time(),
		);

		return array(
			'page'     => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $id, array( 'fields' => 'id,name,link,fan_count,followers_count' ), $token ) ),
			'reels'    => Analytics::part( $this->reels( $id, $token ) ),
			'insights' => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $id . '/insights', array( 'metric' => self::INSIGHTS ) + $range, $token ) ),
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
