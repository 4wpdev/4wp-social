<?php
/**
 * OAuth callback endpoint (the redirect URI registered in Google / TikTok consoles).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Auth\OAuth_Controller;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/forwp-social/v1/oauth/callback/{platform}
 */
final class Rest_Oauth {

	public const NAMESPACE_V1 = 'forwp-social/v1';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/oauth/callback/(?P<platform>[a-z0-9.\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'callback' ),
				// Public by design: the provider redirects the browser here. Trust comes from the single-use state transient.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Finish OAuth and send the browser back to the Accounts tab.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function callback( WP_REST_Request $request ): void {
		$target = OAuth_Controller::instance()->complete(
			(string) $request['platform'],
			sanitize_text_field( (string) $request->get_param( 'code' ) ),
			sanitize_text_field( (string) $request->get_param( 'state' ) ),
			sanitize_text_field( (string) ( $request->get_param( 'error_description' ) ? $request->get_param( 'error_description' ) : $request->get_param( 'error' ) ) )
		);

		wp_safe_redirect( $target );
		exit;
	}
}
