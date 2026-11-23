<?php
/**
 * YouTube: channel statistics (Data API), daily report and per-video report (YouTube Analytics API v2).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Api\Http;
use ForWP\Social\Auth\Google_OAuth_Provider;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Contracts\Analytics_Daily_Interface;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Needs `youtube.force-ssl` (channel) and `yt-analytics.readonly` (report).
 */
final class YouTube_Source implements Analytics_Source_Interface, Analytics_Daily_Interface {

	private const DATA_API      = 'https://www.googleapis.com/youtube/v3';
	private const ANALYTICS_API = 'https://youtubeanalytics.googleapis.com/v2/reports';
	private const METRICS       = 'views,estimatedMinutesWatched,averageViewDuration,likes,comments,shares,subscribersGained,subscribersLost';
	private const HISTORY_DAYS  = 365;
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
		return __( 'Channel totals, daily report and views per video for the chosen period. The report needs the yt-analytics.readonly scope: add it in Google Cloud → Data Access, then Reconnect.', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$token = OAuth_Controller::instance()->get_access_token( $this->slug() );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// Daily rows and videos are loaded by the panel (Metrics_Store), the snapshot keeps channel totals.
		return array(
			'channel' => Analytics::part( $this->get( self::DATA_API . '/channels?part=snippet,statistics&mine=true', $token ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 */
	public function daily( string $start, string $end ) {
		$token = OAuth_Controller::instance()->get_access_token( $this->slug() );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$report = $this->get(
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
		);
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return self::daily_rows( $report );
	}

	/**
	 * Report (columnHeaders + rows) → day => metrics, with net subscribers.
	 *
	 * @param array<string, mixed> $report Analytics API answer.
	 * @return array<string, array<string, float>>
	 */
	public static function daily_rows( array $report ): array {
		$cols = array();
		foreach ( (array) ( $report['columnHeaders'] ?? array() ) as $i => $col ) {
			$cols[ (string) ( $col['name'] ?? $i ) ] = $i;
		}

		$out = array();
		foreach ( (array) ( $report['rows'] ?? array() ) as $raw ) {
			$day = (string) ( $raw[ $cols['day'] ?? 0 ] ?? '' );
			if ( '' === $day ) {
				continue;
			}
			$row = array();
			foreach ( $cols as $name => $i ) {
				if ( 'day' !== $name ) {
					$row[ $name ] = (float) ( $raw[ $i ] ?? 0 );
				}
			}
			$row['subscribersNet'] = ( $row['subscribersGained'] ?? 0 ) - ( $row['subscribersLost'] ?? 0 );
			$out[ $day ]           = $row;
		}

		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function metrics(): array {
		return array(
			'views'                   => array(
				'label' => __( 'Views', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
			'estimatedMinutesWatched' => array(
				'label' => __( 'Minutes watched', '4wp-social' ),
				'agg'   => 'sum',
			),
			'averageViewDuration'     => array(
				'label'  => __( 'Avg view duration', '4wp-social' ),
				'agg'    => 'avg',
				'weight' => 'views',
				'format' => 'time',
			),
			'likes'                   => array(
				'label' => __( 'Likes', '4wp-social' ),
				'agg'   => 'sum',
			),
			'comments'                => array(
				'label' => __( 'Comments', '4wp-social' ),
				'agg'   => 'sum',
			),
			'shares'                  => array(
				'label' => __( 'Shares', '4wp-social' ),
				'agg'   => 'sum',
			),
			'subscribersNet'          => array(
				'label' => __( 'Net subscribers', '4wp-social' ),
				'agg'   => 'sum',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function history_days(): int {
		return self::HISTORY_DAYS;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @param int    $limit Max videos.
	 */
	public function items( string $start, string $end, int $limit ) {
		$token = OAuth_Controller::instance()->get_access_token( $this->slug() );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$videos = $this->videos( $start, $end, $token, max( 1, min( self::VIDEO_LIMIT, $limit ) ) );

		return is_wp_error( $videos ) ? $videos : $videos['rows'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function item_columns(): array {
		return array(
			'views'                   => array(
				'label'  => __( 'Views', '4wp-social' ),
				'format' => 'num',
			),
			'estimatedMinutesWatched' => array(
				'label'  => __( 'Watch time (h)', '4wp-social' ),
				'format' => 'hours',
			),
			'averageViewDuration'     => array(
				'label'  => __( 'Avg view', '4wp-social' ),
				'format' => 'time',
			),
			'likes'                   => array(
				'label'  => __( 'Likes', '4wp-social' ),
				'format' => 'num',
			),
			'comments'                => array(
				'label'  => __( 'Comments', '4wp-social' ),
				'format' => 'num',
			),
			'subscribersGained'       => array(
				'label'  => __( 'Subscribers', '4wp-social' ),
				'format' => 'num',
			),
		);
	}

	/**
	 * Per-video report for the period, joined with title, thumbnail, duration and publish date.
	 *
	 * @param string $start Start date (Y-m-d).
	 * @param string $end   End date (Y-m-d).
	 * @param string $token Access token.
	 * @param int    $limit Max videos.
	 * @return array{start: string, end: string, rows: array<int, array<string, mixed>>}|WP_Error
	 */
	private function videos( string $start, string $end, string $token, int $limit = self::VIDEO_LIMIT ) {
		$report = $this->get(
			add_query_arg(
				array(
					'ids'        => 'channel==MINE',
					'startDate'  => $start,
					'endDate'    => $end,
					'metrics'    => self::VIDEO_METRICS,
					'dimensions' => 'video',
					'sort'       => '-views',
					'maxResults' => $limit,
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
			$row = array(
				'id'  => $id,
				'url' => 'https://www.youtube.com/watch?v=' . rawurlencode( $id ),
			);
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
				$thumbs                      = (array) ( $item['snippet']['thumbnails'] ?? array() );
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
