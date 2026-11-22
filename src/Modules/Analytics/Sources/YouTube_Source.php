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
	private const VIDEO_METRICS = 'views,estimatedMinutesWatched,averageViewDuration,likes,comments,subscribersGained';
	private const VIDEO_LIMIT   = 200;

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
		return __( 'Channel totals, a 28-day daily report and views per video. The report needs the yt-analytics.readonly scope: add it in Google Cloud → Data Access, then Reconnect.', '4wp-social' );
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
			'videos'  => Analytics::part( $this->videos( $start, $end, $token ) ),
		);
	}

	/**
	 * Per-video report for the period, joined with title, thumbnail, duration and publish date.
	 *
	 * @param string $start Start date (Y-m-d).
	 * @param string $end   End date (Y-m-d).
	 * @param string $token Access token.
	 * @return array{start: string, end: string, rows: array<int, array<string, mixed>>}|WP_Error
	 */
	private function videos( string $start, string $end, string $token ) {
		$report = $this->get(
			add_query_arg(
				array(
					'ids'        => 'channel==MINE',
					'startDate'  => $start,
					'endDate'    => $end,
					'metrics'    => self::VIDEO_METRICS,
					'dimensions' => 'video',
					'sort'       => '-views',
					'maxResults' => self::VIDEO_LIMIT,
				),
				self::ANALYTICS_API
			),
			$token
		);
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		$cols = array();
		foreach ( (array) ( $report['columnHeaders'] ?? array() ) as $i => $col ) {
			$cols[ (string) ( $col['name'] ?? $i ) ] = $i;
		}

		$rows = array();
		foreach ( (array) ( $report['rows'] ?? array() ) as $raw ) {
			$id = (string) ( $raw[ $cols['video'] ?? 0 ] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$row = array( 'id' => $id );
			foreach ( explode( ',', self::VIDEO_METRICS ) as $metric ) {
				$row[ $metric ] = isset( $cols[ $metric ] ) ? (float) ( $raw[ $cols[ $metric ] ] ?? 0 ) : 0.0;
			}
			$rows[ $id ] = $row;
		}

		// Video details, 50 ids per request. A failed batch leaves the ids without titles.
		foreach ( array_chunk( array_keys( $rows ), 50 ) as $ids ) {
			$details = $this->get( self::DATA_API . '/videos?part=snippet,contentDetails&maxResults=50&id=' . rawurlencode( implode( ',', $ids ) ), $token );
			if ( is_wp_error( $details ) ) {
				continue;
			}
			foreach ( (array) ( $details['items'] ?? array() ) as $item ) {
				$id = (string) ( $item['id'] ?? '' );
				if ( ! isset( $rows[ $id ] ) ) {
					continue;
				}
				$thumbs                    = (array) ( $item['snippet']['thumbnails'] ?? array() );
				$rows[ $id ]['title']        = (string) ( $item['snippet']['title'] ?? '' );
				$rows[ $id ]['published_at'] = (string) ( $item['snippet']['publishedAt'] ?? '' );
				$rows[ $id ]['thumbnail']    = (string) ( $thumbs['default']['url'] ?? $thumbs['medium']['url'] ?? '' );
				$rows[ $id ]['duration']     = self::seconds( (string) ( $item['contentDetails']['duration'] ?? '' ) );
			}
		}

		return array(
			'start' => $start,
			'end'   => $end,
			'rows'  => array_values( $rows ),
		);
	}

	/**
	 * ISO 8601 duration (PT1M5S) to seconds.
	 *
	 * @param string $iso Duration.
	 */
	private static function seconds( string $iso ): int {
		if ( ! preg_match( '/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $iso, $m ) ) {
			return 0;
		}

		return ( (int) ( $m[1] ?? 0 ) ) * 86400 + ( (int) ( $m[2] ?? 0 ) ) * 3600 + ( (int) ( $m[3] ?? 0 ) ) * 60 + (int) ( $m[4] ?? 0 );
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
