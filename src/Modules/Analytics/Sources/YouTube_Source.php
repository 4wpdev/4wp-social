<?php
/**
 * YouTube: channel statistics (Data API) and daily report (YouTube Analytics API v2).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Api\Http;
use ForWP\Social\Auth\Google_OAuth_Provider;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Needs `youtube.force-ssl` (channel) and `yt-analytics.readonly` (report).
 */
final class YouTube_Source implements Analytics_Source_Interface {

	private const DATA_API      = 'https://www.googleapis.com/youtube/v3';
	private const ANALYTICS_API = 'https://youtubeanalytics.googleapis.com/v2/reports';
	private const METRICS       = 'views,estimatedMinutesWatched,averageViewDuration,likes,comments,shares,subscribersGained,subscribersLost';
	private const DAYS          = 28;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return Google_OAuth_Provider::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'YouTube', '4wp-social' );
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
		return __( 'Channel totals and a 28-day daily report. The report needs the yt-analytics.readonly scope: add it in Google Cloud → Data Access, then Reconnect.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$token = OAuth_Controller::instance()->get_access_token( $this->slug() );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		list( $start, $end ) = Analytics::range( self::DAYS, time() );

		return array(
			'channel' => Analytics::part( $this->get( self::DATA_API . '/channels?part=snippet,statistics&mine=true', $token ) ),
			'daily'   => Analytics::part(
				$this->get(
					add_query_arg(
						array(
							'ids'        => 'channel==MINE',
							'startDate'  => $start,
							'endDate'    => $end,
							'metrics'    => self::METRICS,
							'dimensions' => 'day',
							'sort'       => 'day',
						),
						self::ANALYTICS_API
					),
					$token
				)
			),
		);
	}

	/**
	 * GET with the bearer token.
	 *
	 * @param string $url   URL.
	 * @param string $token Access token.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get( string $url, string $token ) {
		$res = Http::request( 'GET', $url, array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] ) {
			return new WP_Error( 'forwp_social_youtube_analytics', Http::error_message( $res, __( 'YouTube request failed.', '4wp-social' ) ) );
		}

		return (array) $res['json'];
	}
}
