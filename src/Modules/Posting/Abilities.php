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
				'description' => __( 'Cross-post videos and articles to social platforms and read publishing status.', '4wp-social' ),
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

		$readonly = array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
		);

		wp_register_ability(
			'forwp-social/platforms',
			array(
				'label'               => __( 'List social platforms', '4wp-social' ),
				'description'         => __( 'Every platform of the site, api and manual: its key for other abilities, mode, connector, whether the account is connected and whether the platform is on.', '4wp-social' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'platform'  => array(
								'type'        => 'string',
								'description' => __( 'Platform key, as in meta.json.', '4wp-social' ),
							),
							'label'     => array( 'type' => 'string' ),
							'mode'      => array(
								'type'        => 'string',
								'enum'        => array( Status_Index::MODE_API, Status_Index::MODE_MANUAL ),
								'description' => __( 'api: the queue publishes. manual: a person publishes.', '4wp-social' ),
							),
							'connector' => array(
								'type'        => 'string',
								'description' => __( 'Bound connector, empty for manual.', '4wp-social' ),
							),
							'ready'     => array(
								'type'        => 'boolean',
								'description' => __( 'The connector account is connected. Always false for manual.', '4wp-social' ),
							),
							'enabled'   => array( 'type' => 'boolean' ),
							'url'       => array(
								'type'        => 'string',
								'description' => __( 'Where to open the platform.', '4wp-social' ),
							),
						),
					),
				),
				'execute_callback'    => array( self::class, 'platforms' ),
				'permission_callback' => static function () {
					return current_user_can( 'publish_posts' );
				},
				'meta'                => $readonly,
			)
		);

		wp_register_ability(
			'forwp-social/calendar',
			array(
				'label'               => __( 'Publishing calendar', '4wp-social' ),
				'description'         => __( 'Content published or updated on the site, plus cross-posting tasks and done publications of one month, api and manual, with counts per state. Use for "what was published in October".', '4wp-social' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'month' => array(
							'type'        => 'string',
							'pattern'     => '^[0-9]{4}-(0[1-9]|1[0-2])$',
							'description' => __( 'Month as YYYY-MM. Default: the current month.', '4wp-social' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'month'  => array( 'type' => 'string' ),
						'stats'  => array(
							'type'        => 'object',
							'description' => __( 'Counts: published, scheduled, waiting (for manual posting), failed.', '4wp-social' ),
						),
						'events' => array(
							'type'        => 'array',
							'description' => __( 'date and time (site time zone), post_id, title, edit_url, platform, status, mode, url.', '4wp-social' ),
							'items'       => array( 'type' => 'object' ),
						),
						'site'   => array(
							'type'        => 'array',
							'description' => __( 'Content published or updated on the site: date, time, post_id, post_type, type_label, action (publish or update), title, edit_url, url. Only the last update of a post is known.', '4wp-social' ),
							'items'       => array( 'type' => 'object' ),
						),
					),
				),
				'execute_callback'    => array( self::class, 'calendar' ),
				'permission_callback' => array( self::class, 'can_read_all' ),
				'meta'                => $readonly,
			)
		);

		wp_register_ability(
			'forwp-social/scheduled',
			array(
				'label'               => __( 'Scheduled publications', '4wp-social' ),
				'description'         => __( 'What is planned, queued or running, across all months, soonest first. Use for "what goes out next". Plans without a date are not listed.', '4wp-social' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'platform' => array(
							'type'        => 'string',
							'description' => __( 'Only this platform key. Default: all.', '4wp-social' ),
						),
						'limit'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 200,
							'default'     => 50,
							'description' => __( 'Max items.', '4wp-social' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'  => array( 'type' => 'integer' ),
							'title'    => array( 'type' => 'string' ),
							'edit_url' => array( 'type' => 'string' ),
							'platform' => array( 'type' => 'string' ),
							'status'   => array(
								'type' => 'string',
								'enum' => array( Status_Index::PLANNED, Job_Repository::QUEUED, Job_Repository::PROCESSING ),
							),
							'mode'     => array( 'type' => 'string' ),
							'run_at'   => array(
								'type'        => 'string',
								'description' => __( 'When it goes out, ISO 8601 UTC.', '4wp-social' ),
							),
						),
					),
				),
				'execute_callback'    => array( self::class, 'scheduled' ),
				'permission_callback' => array( self::class, 'can_read_all' ),
				'meta'                => $readonly,
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
				'description'         => __( 'Queue a post for YouTube / TikTok / Bluesky / DEV.to (DEV.to needs source "package"). source "package" uses meta.json (all api platforms when none given); "post" uses the media chosen in the editor box.', '4wp-social' ),
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
	 * Calendar and scheduled work name every post, drafts of other authors included.
	 */
	public static function can_read_all(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Every platform with its binding.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function platforms(): array {
		$out = array();
		foreach ( Platform_Registry::all( false ) as $key => $row ) {
			$adapter = Adapter_Registry::get( (string) $key );
			$out[]   = array(
				'platform'  => (string) $key,
				'label'     => (string) $row['label'],
				'mode'      => (string) $row['mode'],
				'connector' => (string) ( $row['connector'] ?? '' ),
				'ready'     => $adapter ? $adapter->is_ready() : false,
				'enabled'   => ! empty( $row['enabled'] ),
				'url'       => (string) $row['url'],
			);
		}

		return $out;
	}

	/**
	 * One month of the calendar.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array{month: string, stats: array<string, int>, events: array<int, array<string, mixed>>}
	 */
	public static function calendar( $input = null ): array {
		$month  = Calendar::month( (string) ( $input['month'] ?? '' ) );
		$events = Calendar::events( $month );

		return array(
			'month'  => $month,
			'stats'  => Calendar::stats( $events ),
			'events' => $events,
			'site'   => Calendar::site_events( $month ),
		);
	}

	/**
	 * What goes out next.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<int, array<string, mixed>>
	 */
	public static function scheduled( $input = null ): array {
		return Calendar::scheduled( strtolower( (string) ( $input['platform'] ?? '' ) ), max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) ) );
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
