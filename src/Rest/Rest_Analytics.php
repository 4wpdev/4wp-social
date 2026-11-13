<?php
/**
 * REST: analytics snapshots (read) and fetch one source now.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Modules\Analytics\Analytics;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /analytics, POST /analytics/refresh.
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
}
