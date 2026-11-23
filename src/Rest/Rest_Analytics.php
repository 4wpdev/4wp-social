<?php
/**
 * REST: analytics snapshots (read), fetch one source now, items of a period.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Contracts\Analytics_Daily_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Metrics_Store;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /analytics, POST /analytics/refresh, GET /analytics/items.
 */
final class Rest_Analytics {

	/**
	 * Hook the routes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$can = static fn(): bool => current_user_can( 'manage_options' );

		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => $can,
				'args'                => array(
					'source' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/analytics/items',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'items' ),
				'permission_callback' => $can,
				'args'                => array(
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
					'start'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'end'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'limit'  => array(
						'type'    => 'integer',
						'default' => 5,
					),
				),
			)
		);
		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/analytics/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh' ),
				'permission_callback' => $can,
				'args'                => array(
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Sources with status and last snapshot.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$only      = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$snapshots = Analytics::snapshots();
		$out       = array();

		foreach ( Analytics::sources() as $slug => $source ) {
			if ( '' !== $only && $only !== $slug ) {
				continue;
			}
			$out[] = array(
				'source'     => $slug,
				'label'      => $source->label(),
				'status'     => $source->status(),
				'fetched_at' => isset( $snapshots[ $slug ] ) ? gmdate( 'c', (int) $snapshots[ $slug ]['fetched_at'] ) : null,
				'data'       => $snapshots[ $slug ]['data'] ?? null,
			);
		}

		return new WP_REST_Response( $out );
	}

	/**
	 * Fetch one source now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function refresh( WP_REST_Request $request ) {
		$slug   = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$result = Analytics::refresh( $slug );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );

			return $result;
		}

		return new WP_REST_Response(
			array(
				'source' => $slug,
				'data'   => $result,
			)
		);
	}

	/**
	 * Items (videos) of a period, e.g. top 5 of one day. Cached for a day.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function items( WP_REST_Request $request ) {
		$slug   = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$start  = sanitize_text_field( (string) $request->get_param( 'start' ) );
		$end    = sanitize_text_field( (string) $request->get_param( 'end' ) );
		$source = Analytics::source( $slug );

		if ( ! $source instanceof Analytics_Daily_Interface || ! in_array( $source->status(), array( Analytics::READY, Analytics::MANUAL ), true ) ) {
			return new \WP_Error( 'forwp_social_analytics_source', __( 'This source is not connected.', '4wp-social' ), array( 'status' => 400 ) );
		}
		if ( ! Metrics_Store::is_day( $start ) || ! Metrics_Store::is_day( $end ) ) {
			return new \WP_Error( 'forwp_social_analytics_range', __( 'Dates must be Y-m-d.', '4wp-social' ), array( 'status' => 400 ) );
		}

		$items = Metrics_Store::items( $slug, $source, $start, $end, max( 1, min( 50, (int) $request->get_param( 'limit' ) ) ) );
		if ( is_wp_error( $items ) ) {
			$items->add_data( array( 'status' => 502 ) );

			return $items;
		}

		return new WP_REST_Response(
			array(
				'source' => $slug,
				'start'  => $start,
				'end'    => $end,
				'items'  => $items,
			)
		);
	}
}
