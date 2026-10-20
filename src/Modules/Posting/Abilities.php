<?php
/**
 * Abilities for AI agents (4WP MCP Connector contract): forwp-social/*.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Rest\Rest_Jobs;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registered only when the Abilities API exists (WP 6.9+). The connector decides what the agent sees.
 */
final class Abilities {

	public const CATEGORY = 'forwp-social';

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register' ) );
	}

	/**
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( '4WP Social', '4wp-social' ),
				'description' => __( 'Cross-post videos to social platforms and read publishing status.', '4wp-social' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'forwp-social/platforms',
			array(
				'label'               => __( 'List social platforms', '4wp-social' ),
				'description'         => __( 'Platforms the site can publish to and whether each account is connected.', '4wp-social' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array( 'type' => 'array' ),
				'execute_callback'    => array( self::class, 'platforms' ),
				'permission_callback' => static function () {
					return current_user_can( 'publish_posts' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'forwp-social/status',
			array(
				'label'               => __( 'Publishing status of a post', '4wp-social' ),
				'description'         => __( 'Jobs of a post per platform: status, URL, error.', '4wp-social' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'array' ),
				'execute_callback'    => array( self::class, 'status' ),
				'permission_callback' => static function ( $input ) {
					return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'forwp-social/publish',
			array(
				'label'               => __( 'Cross-post a post', '4wp-social' ),
				'description'         => __( 'Queue a post for YouTube / TikTok. source "package" uses meta.json (all api platforms when none given); "post" uses the media chosen in the editor box.', '4wp-social' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'platforms' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'source'    => array(
							'type'    => 'string',
							'enum'    => array( Job_Repository::SOURCE_PACKAGE, Job_Repository::SOURCE_POST ),
							'default' => Job_Repository::SOURCE_PACKAGE,
						),
						'privacy'   => array(
							'type' => 'string',
							'enum' => array( 'public', 'unlisted', 'private' ),
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( self::class, 'publish' ),
				'permission_callback' => static function ( $input ) {
					return current_user_can( 'publish_posts' ) && current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	/**
	 * @return array<int, array{platform: string, label: string, ready: bool}>
	 */
	public static function platforms(): array {
		$out = array();
		foreach ( Adapter_Registry::all() as $key => $adapter ) {
			$out[] = array(
				'platform' => $key,
				'label'    => $adapter->get_label(),
				'ready'    => $adapter->is_ready(),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<int, array<string, mixed>>
	 */
	public static function status( $input ): array {
		return array_map( array( Rest_Jobs::class, 'shape' ), ( new Job_Repository() )->recent( (int) ( $input['post_id'] ?? 0 ), 20 ) );
	}

	/**
	 * Never forces a re-post: agents cannot publish the same video twice (idempotent).
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function publish( $input ) {
		$options = array();
		if ( ! empty( $input['privacy'] ) ) {
			$options['privacy'] = (string) $input['privacy'];
		}

		return Rest_Jobs::queue(
			(int) ( $input['post_id'] ?? 0 ),
			array_map( 'strval', (array) ( $input['platforms'] ?? array() ) ),
			(string) ( $input['source'] ?? Job_Repository::SOURCE_PACKAGE ),
			$options,
			false
		);
	}
}
