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

	private const INSIGHTS = 'page_impressions_unique,page_post_engagements,page_video_views';

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
		return __( 'Page followers, the last 25 Reels and 28-day Page insights. Insights need the read_insights permission in the Meta configuration (to confirm).', '4wp-social' );
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

		return array(
			'page'     => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $page['id'], array( 'fields' => 'id,name,link,fan_count,followers_count' ), $token ) ),
			'reels'    => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$page['id'] . '/video_reels',
					array(
						'fields' => 'id,description,created_time,permalink_url,length',
						'limit'  => 25,
					),
					$token
				)
			),
			'insights' => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$page['id'] . '/insights',
					array(
						'metric' => self::INSIGHTS,
						'period' => 'days_28',
					),
					$token
				)
			),
		);
	}
}
