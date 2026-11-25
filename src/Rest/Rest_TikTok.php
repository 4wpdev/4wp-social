<?php
/**
 * TikTok post box data: creator info (account, privacy options, limits), caption and video preview.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Api\TikTok_Client;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Modules\Posting\Adapters\TikTok_Adapter;
use ForWP\Social\Modules\Posting\Package_Importer;
use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Post_Reader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET /forwp-social/v1/tiktok/creator
 */
final class Rest_TikTok {

	/**
	 * Hook the routes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		register_rest_route(
			'forwp-social/v1',
			'/tiktok/creator',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'creator' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return current_user_can( 'edit_post', (int) $request['post_id'] );
				},
				'args'                => array(
					'post_id'  => array(
						'type'     => 'integer',
						'required' => true,
					),
					'source'   => array(
						'type'    => 'string',
						'enum'    => array( 'post', 'package' ),
						'default' => 'post',
					),
					'video_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Creator info + caption + preview for the TikTok panel of the post box.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function creator( WP_REST_Request $request ) {
		$token = OAuth_Controller::instance()->get_access_token( TikTok_Adapter::SLUG );
		if ( is_wp_error( $token ) ) {
			return new WP_Error( $token->get_error_code(), $token->get_error_message(), array( 'status' => 400 ) );
		}
		$info = ( new TikTok_Client( $token ) )->creator_info();
		if ( is_wp_error( $info ) ) {
			return new WP_Error( 'forwp_social_tiktok_creator', $info->get_error_message(), array( 'status' => 400 ) );
		}

		$post_id  = (int) $request['post_id'];
		$package  = 'package' === $request['source'];
		$reader   = $package ? new Package_Reader() : new Post_Reader();
		$built    = $reader->build( $post_id, TikTok_Adapter::SLUG, false );
		$video_id = $package ? self::package_video_id( $post_id ) : (int) $request['video_id'];
		if ( ! $package && ! $video_id ) {
			$video_id = (int) ( Post_Reader::saved( $post_id )['video_id'] ?? 0 );
		}
		$meta = $video_id ? wp_get_attachment_metadata( $video_id ) : array();

		return new WP_REST_Response(
			array(
				'nickname'         => (string) ( $info['creator_nickname'] ?? '' ),
				'username'         => (string) ( $info['creator_username'] ?? '' ),
				'avatar'           => (string) ( $info['creator_avatar_url'] ?? '' ),
				'privacy_options'  => array_values( array_intersect( array_map( 'strval', (array) ( $info['privacy_level_options'] ?? array() ) ), TikTok_Adapter::PRIVACY_LEVELS ) ),
				'comment_disabled' => ! empty( $info['comment_disabled'] ),
				'duet_disabled'    => ! empty( $info['duet_disabled'] ),
				'stitch_disabled'  => ! empty( $info['stitch_disabled'] ),
				'max_duration'     => (int) ( $info['max_video_post_duration_sec'] ?? 0 ),
				'caption'          => is_wp_error( $built ) ? '' : TikTok_Adapter::caption( $built ),
				'video_url'        => $video_id ? (string) wp_get_attachment_url( $video_id ) : '',
				'duration'         => is_array( $meta ) ? (int) ( $meta['length'] ?? 0 ) : 0,
			),
			200
		);
	}

	/**
	 * Video attachment of the TikTok item (or the borrowed one) of a package post.
	 *
	 * @param int $post_id Post id.
	 */
	private static function package_video_id( int $post_id ): int {
		$snapshot = Package_Importer::snapshot( $post_id );
		$item     = is_array( $snapshot[ TikTok_Adapter::SLUG ] ?? null ) ? $snapshot[ TikTok_Adapter::SLUG ] : Package_Reader::borrowed_item( $snapshot );

		return is_array( $item ) ? (int) ( $item['video_id'] ?? 0 ) : 0;
	}
}
