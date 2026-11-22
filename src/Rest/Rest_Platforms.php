<?php
/**
 * Platforms list: add, edit, delete, reorder.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Modules\Posting\Adapter_Registry;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * /forwp-social/v1/platforms
 */
final class Rest_Platforms {

	private const FIELDS = array( 'label', 'connector', 'url', 'format', 'icon', 'types', 'enabled' );

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
			'/platforms',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'payload' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => $can,
				),
			)
		);
		// Before the key route: "order" is not a platform.
		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/platforms-order',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'reorder' ),
				'permission_callback' => $can,
				'args'                => array(
					'keys' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/platforms/(?P<key>[a-z0-9][a-z0-9.\-]*)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete' ),
					'permission_callback' => $can,
				),
			)
		);
	}

	/**
	 * The list and the connectors it can bind.
	 *
	 * @return array{platforms: array<int, array<string, mixed>>, connectors: array<int, array<string, mixed>>, accounts_url: string}
	 */
	public static function payload(): array {
		$connectors = array();
		foreach ( Adapter_Registry::connectors() as $slug => $adapter ) {
			$connectors[] = array(
				'slug'  => (string) $slug,
				'label' => $adapter->get_label(),
				'ready' => $adapter->is_ready(),
			);
		}

		return array(
			'platforms'    => array_map(
				static fn( array $row ): array => $row + array( 'icon_html' => Platform_Icons::render( (string) $row['key'] ) ),
				array_values( Platform_Registry::all( false ) )
			),
			'connectors'   => $connectors,
			'post_types'   => self::post_types(),
			'accounts_url' => admin_url( 'admin.php?page=forwp-social-settings&tab=accounts' ),
		);
	}

	/**
	 * Content types a platform can take: public post types with an admin screen.
	 *
	 * @return array<int, array{key: string, label: string, icon: string}>
	 */
	private static function post_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $object ) {
			if ( 'attachment' === $object->name || ! $object->show_ui ) {
				continue;
			}
			$icon  = is_string( $object->menu_icon ) && 0 === strpos( $object->menu_icon, 'dashicons-' ) ? $object->menu_icon : ( 'page' === $object->name ? 'dashicons-admin-page' : 'dashicons-admin-post' );
			$out[] = array(
				'key'   => $object->name,
				'label' => (string) $object->labels->singular_name,
				'icon'  => $icon,
			);
		}

		return $out;
	}

	/**
	 * Add a platform.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$result = Platform_Registry::create( array( 'key' => (string) $request['key'] ) + self::fields( $request ) );

		return is_wp_error( $result ) ? $result : self::payload();
	}

	/**
	 * Change a platform.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$key    = (string) $request['key'];
		$new    = (string) $request->get_param( 'new_key' );
		if ( '' !== $new && $new !== $key ) {
			$renamed = Platform_Registry::rename( $key, $new );
			if ( is_wp_error( $renamed ) ) {
				return $renamed;
			}
			$key = (string) $renamed['key'];
		}
		$result = Platform_Registry::update( $key, self::fields( $request ) );

		return is_wp_error( $result ) ? $result : self::payload();
	}

	/**
	 * Remove a platform.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$result = Platform_Registry::delete( (string) $request['key'] );

		return is_wp_error( $result ) ? $result : self::payload();
	}

	/**
	 * Save the dragged order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public static function reorder( WP_REST_Request $request ): array {
		Platform_Registry::reorder( array_map( 'strval', (array) $request['keys'] ) );

		return self::payload();
	}

	/**
	 * Only the fields the request sent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function fields( WP_REST_Request $request ): array {
		$out = array();
		foreach ( self::FIELDS as $field ) {
			if ( $request->has_param( $field ) ) {
				$out[ $field ] = $request[ $field ];
			}
		}

		return $out;
	}
}
