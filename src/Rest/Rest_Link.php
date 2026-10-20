<?php
/**
 * Link a post to a video already on YouTube: search by title, link by id, unlink.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Api\YouTube_Client;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Modules\Posting\Remote_Link;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * /forwp-social/v1/link
 */
final class Rest_Link {

	private const PLATFORM = 'youtube.com';

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
		$post_arg = array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/link/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'search' ),
				'permission_callback' => array( Rest_Jobs::class, 'can_publish' ),
				'args'                => $post_arg + array(
					'q' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/link',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'link' ),
					'permission_callback' => array( Rest_Jobs::class, 'can_publish' ),
					'args'                => $post_arg + array(
						'video' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'unlink' ),
					'permission_callback' => array( Rest_Jobs::class, 'can_publish' ),
					'args'                => $post_arg,
				),
			)
		);
	}

	/**
	 * Channel uploads ranked by the post title (or q).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search( WP_REST_Request $request ) {
		$client = self::client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$uploads = $client->uploads();
		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		$query = '' !== trim( (string) $request['q'] ) ? (string) $request['q'] : get_the_title( (int) $request['post_id'] );

		return new WP_REST_Response( Remote_Link::rank( $query, $uploads ) );
	}

	/**
	 * Link by id or URL; the id is checked on YouTube first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function link( WP_REST_Request $request ) {
		$id = Remote_Link::youtube_id( (string) $request['video'] );
		if ( '' === $id ) {
			return new WP_Error( 'forwp_social_bad_id', __( 'Not a YouTube video ID or link.', '4wp-social' ), array( 'status' => 400 ) );
		}

		$client = self::client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$video = $client->video( $id );
		if ( is_wp_error( $video ) ) {
			$video->add_data( array( 'status' => 404 ) );
			return $video;
		}

		$row          = Remote_Link::link( (int) $request['post_id'], self::PLATFORM, $id, '', 'manual' );
		$row['title'] = $video['title'];

		return new WP_REST_Response( $row );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public static function unlink( WP_REST_Request $request ): WP_REST_Response {
		Remote_Link::unlink( (int) $request['post_id'], self::PLATFORM );

		return new WP_REST_Response( array( 'unlinked' => true ) );
	}

	/**
	 * @return YouTube_Client|WP_Error
	 */
	private static function client() {
		$token = OAuth_Controller::instance()->get_access_token( self::PLATFORM );
		if ( is_wp_error( $token ) ) {
			$token->add_data( array( 'status' => 409 ) );
			return $token;
		}

		return new YouTube_Client( $token );
	}
}
