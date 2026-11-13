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
		return __( 'Followers, the last 25 posts with likes and comments, 28-day account insights. Insights need the instagram_manage_insights permission (to confirm).', '4wp-social' );
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
			'account'  => Analytics::part( Meta_OAuth_Provider::graph( 'GET', $ig, array( 'fields' => 'id,username,followers_count,follows_count,media_count' ), $token ) ),
			'media'    => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$ig . '/media',
					array(
						'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count',
						'limit'  => 25,
					),
					$token
				)
			),
			'insights' => Analytics::part(
				Meta_OAuth_Provider::graph(
					'GET',
					$ig . '/insights',
					array(
						'metric'      => self::INSIGHTS,
						'period'      => 'day',
						'metric_type' => 'total_value',
						'since'       => time() - 28 * DAY_IN_SECONDS,
						'until'       => time(),
					),
					$token
				)
			),
		);
	}
}
