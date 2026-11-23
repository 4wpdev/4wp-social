<?php
/**
 * Google Analytics 4: site traffic by day, top pages and traffic sources (GA4 Data API).
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
use ForWP\Social\Modules\Analytics\Metrics_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the Google connection (scope `analytics.readonly`) and the GA4 Property ID saved on the Analytics page.
 */
final class Google_Analytics_Source implements Analytics_Source_Interface, Analytics_Daily_Interface {

	public const PROPERTY_OPTION = 'forwp_social_ga4_property';

	private const API          = 'https://analyticsdata.googleapis.com/v1beta/properties/';
	private const HISTORY_DAYS = 365;

	/**
	 * GA4 metrics by day.
	 */
	private const DAILY_METRICS = array( 'activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'userEngagementDuration', 'engagedSessions' );

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'analytics.google.com';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Google Analytics', '4wp-social' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): string {
		return OAuth_Controller::instance()->is_connected( Google_OAuth_Provider::SLUG ) && '' !== self::property() ? Analytics::READY : Analytics::NOT_CONNECTED;
	}

	/**
	 * {@inheritDoc}
	 */
	public function note(): string {
		return __( 'Site traffic by day, top pages and traffic sources (GA4 Data API). Needs the Google connection with the analytics.readonly scope (Reconnect after adding it) and the GA4 Property ID below.', '4wp-social' );
	}

	/**
	 * Saved GA4 Property ID (digits only), or ''.
	 */
	public static function property(): string {
		return preg_replace( '/\D/', '', (string) get_option( self::PROPERTY_OPTION, '' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$res = $this->report(
			array(
				'dateRanges' => array(
					array(
						'startDate' => '28daysAgo',
						'endDate'   => 'yesterday',
					),
				),
				'metrics'    => self::names( array( 'activeUsers', 'sessions', 'screenPageViews' ) ),
			)
		);

		return array(
			'property' => self::property(),
			'last_28'  => Analytics::part( $res ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 */
	public function daily( string $start, string $end ) {
		$res = $this->report(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $start,
						'endDate'   => $end,
					),
				),
				'dimensions' => self::names( array( 'date' ) ),
				'metrics'    => self::names( self::DAILY_METRICS ),
				'limit'      => 1000,
			)
		);

		return is_wp_error( $res ) ? $res : self::daily_rows( $res );
	}

	/**
	 * GA4 report by date → day => metrics, plus avgEngagement (seconds per user) and engagementRate (%).
	 *
	 * @param array<string, mixed> $report runReport answer.
	 * @return array<string, array<string, float>>
	 */
	public static function daily_rows( array $report ): array {
		$names = array_map(
			static function ( $h ): string {
				return (string) ( $h['name'] ?? '' );
			},
			(array) ( $report['metricHeaders'] ?? array() )
		);

		$out = array();
		foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
			$date = (string) ( $row['dimensionValues'][0]['value'] ?? '' );
			if ( ! preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date, $m ) ) {
				continue;
			}
			$values = array();
			foreach ( $names as $i => $name ) {
				$values[ $name ] = (float) ( $row['metricValues'][ $i ]['value'] ?? 0 );
			}
			$users                            = $values['activeUsers'] ?? 0.0;
			$sessions                         = $values['sessions'] ?? 0.0;
			$values['avgEngagement']          = $users > 0 ? round( ( $values['userEngagementDuration'] ?? 0.0 ) / $users, 1 ) : 0.0;
			$values['engagementRate']         = $sessions > 0 ? round( 100 * ( $values['engagedSessions'] ?? 0.0 ) / $sessions, 1 ) : 0.0;
			$out[ "{$m[1]}-{$m[2]}-{$m[3]}" ] = $values;
		}
		ksort( $out );

		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function metrics(): array {
		return array(
			'screenPageViews' => array(
				'label' => __( 'Page views', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
			'activeUsers'     => array(
				'label' => __( 'Users (sum by day)', '4wp-social' ),
				'agg'   => 'sum',
			),
			'newUsers'        => array(
				'label' => __( 'New users', '4wp-social' ),
				'agg'   => 'sum',
			),
			'sessions'        => array(
				'label' => __( 'Sessions', '4wp-social' ),
				'agg'   => 'sum',
			),
			'avgEngagement'   => array(
				'label'  => __( 'Avg engagement time', '4wp-social' ),
				'agg'    => 'avg',
				'weight' => 'activeUsers',
				'format' => 'time',
			),
			'engagementRate'  => array(
				'label'  => __( 'Engagement rate, %', '4wp-social' ),
				'agg'    => 'avg',
				'weight' => 'sessions',
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
	 * @param int    $limit Max pages.
	 */
	public function items( string $start, string $end, int $limit ) {
		$res = $this->report(
			array(
				'dateRanges' => array(
					array(
						'startDate' => $start,
						'endDate'   => $end,
					),
				),
				'dimensions' => self::names( array( 'pagePath', 'pageTitle' ) ),
				'metrics'    => self::names( array( 'screenPageViews', 'activeUsers', 'userEngagementDuration' ) ),
				'orderBys'   => array(
					array(
						'metric' => array( 'metricName' => 'screenPageViews' ),
						'desc'   => true,
					),
				),
				'limit'      => max( 1, $limit ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$out = array();
		foreach ( (array) ( $res['rows'] ?? array() ) as $row ) {
			$path  = (string) ( $row['dimensionValues'][0]['value'] ?? '' );
			$users = (float) ( $row['metricValues'][1]['value'] ?? 0 );
			$url   = home_url( $path );
			$post  = url_to_postid( $url );
			$out[] = array(
				'id'            => $path,
				'title'         => (string) ( $row['dimensionValues'][1]['value'] ?? $path ),
				'url'           => $url,
				'thumbnail'     => $post ? (string) get_the_post_thumbnail_url( $post, 'thumbnail' ) : '',
				'published_at'  => $post ? (string) get_post_time( 'c', true, $post ) : '',
				'views'         => (float) ( $row['metricValues'][0]['value'] ?? 0 ),
				'users'         => $users,
				'avgEngagement' => $users > 0 ? round( (float) ( $row['metricValues'][2]['value'] ?? 0 ) / $users, 1 ) : 0.0,
			);
		}

		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function item_columns(): array {
		return array(
			'views'         => array(
				'label'  => __( 'Views', '4wp-social' ),
				'format' => 'num',
			),
			'users'         => array(
				'label'  => __( 'Users', '4wp-social' ),
				'format' => 'num',
			),
			'avgEngagement' => array(
				'label'  => __( 'Avg time', '4wp-social' ),
				'format' => 'time',
			),
		);
	}

	/**
	 * Traffic sources of a period: channel groups and top source / medium. Cached until the next collection.
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @return array{channels: array<int, array{name: string, sessions: float, users: float}>, sources: array<int, array{name: string, sessions: float, users: float}>}|WP_Error
	 */
	public function traffic( string $start, string $end ) {
		$key    = 'forwp_social_ga4_traffic_' . md5( implode( '|', array( $start, $end, (string) get_option( Metrics_Store::ITEMS_VERSION, '' ) ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = array();
		foreach ( array(
			'channels' => array( 'sessionDefaultChannelGroup', 20 ),
			'sources'  => array( 'sessionSourceMedium', 10 ),
		) as $part => $def ) {
			$res = $this->report(
				array(
					'dateRanges' => array(
						array(
							'startDate' => $start,
							'endDate'   => $end,
						),
					),
					'dimensions' => self::names( array( $def[0] ) ),
					'metrics'    => self::names( array( 'sessions', 'activeUsers' ) ),
					'orderBys'   => array(
						array(
							'metric' => array( 'metricName' => 'sessions' ),
							'desc'   => true,
						),
					),
					'limit'      => $def[1],
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$out[ $part ] = array();
			foreach ( (array) ( $res['rows'] ?? array() ) as $row ) {
				$out[ $part ][] = array(
					'name'     => (string) ( $row['dimensionValues'][0]['value'] ?? '' ),
					'sessions' => (float) ( $row['metricValues'][0]['value'] ?? 0 ),
					'users'    => (float) ( $row['metricValues'][1]['value'] ?? 0 ),
				);
			}
		}

		set_transient( $key, $out, DAY_IN_SECONDS );

		return $out;
	}

	/**
	 * One runReport call.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function report( array $body ) {
		$property = self::property();
		if ( '' === $property ) {
			return new WP_Error( 'forwp_social_ga4', __( 'Save the GA4 Property ID first.', '4wp-social' ) );
		}
		$token = OAuth_Controller::instance()->get_access_token( Google_OAuth_Provider::SLUG );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$res = Http::json( 'POST', self::API . $property . ':runReport', $body, array( 'Authorization' => 'Bearer ' . $token ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] ) {
			return new WP_Error( 'forwp_social_ga4', Http::error_message( $res, __( 'Google Analytics request failed.', '4wp-social' ) ) );
		}

		return (array) $res['json'];
	}

	/**
	 * [ 'a', 'b' ] → [ { name: a }, { name: b } ].
	 *
	 * @param string[] $names Names.
	 * @return array<int, array{name: string}>
	 */
	private static function names( array $names ): array {
		return array_map(
			static function ( string $name ): array {
				return array( 'name' => $name );
			},
			$names
		);
	}
}
