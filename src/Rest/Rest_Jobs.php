<?php
/**
 * Publishing jobs: queue by button / MCP, list, retry.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Rest;

use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Posting_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * /forwp-social/v1/jobs
 */
final class Rest_Jobs {

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
			Rest_Oauth::NAMESPACE_V1,
			'/jobs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_jobs' ),
					'permission_callback' => array( self::class, 'can_read' ),
					'args'                => array(
						'post_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => array( self::class, 'can_publish' ),
					'args'                => array(
						'post_id'   => array(
							'type'     => 'integer',
							'required' => true,
						),
						'platforms' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
						'source'    => array(
							'type'    => 'string',
							'enum'    => array( Job_Repository::SOURCE_POST, Job_Repository::SOURCE_PACKAGE ),
							'default' => Job_Repository::SOURCE_POST,
						),
						'options'   => array(
							'type'    => 'object',
							'default' => array(),
						),
						'force'     => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			Rest_Oauth::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)/retry',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'retry' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public static function can_read( WP_REST_Request $request ): bool {
		$post_id = (int) $request['post_id'];

		return $post_id > 0 ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'manage_options' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public static function can_publish( WP_REST_Request $request ): bool {
		return current_user_can( 'publish_posts' ) && current_user_can( 'edit_post', (int) $request['post_id'] );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 */
	public static function list_jobs( WP_REST_Request $request ): WP_REST_Response {
		$rows = ( new Job_Repository() )->recent( (int) $request['post_id'], 50 );

		return new WP_REST_Response( array_map( array( self::class, 'shape' ), $rows ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$report = self::queue(
			(int) $request['post_id'],
			array_map( 'strval', (array) $request['platforms'] ),
			(string) $request['source'],
			is_array( $request['options'] ) ? $request['options'] : array(),
			(bool) $request['force']
		);

		return is_wp_error( $report ) ? $report : new WP_REST_Response( $report );
	}

	/**
	 * Shared by REST and abilities.
	 *
	 * @param int                  $post_id   Post id.
	 * @param array<int, string>   $platforms Platform keys (empty + package = all api platforms of meta.json).
	 * @param string               $source    post|package.
	 * @param array<string, mixed> $options   Post source options.
	 * @param bool                 $force     Publish again.
	 * @return array<string, array{job_id: int, skipped: string}>|WP_Error
	 */
	public static function queue( int $post_id, array $platforms, string $source, array $options, bool $force ) {
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'forwp_social_no_post', __( 'Post not found.', '4wp-social' ), array( 'status' => 404 ) );
		}

		$service = new Posting_Service();
		if ( Job_Repository::SOURCE_PACKAGE === $source ) {
			return $service->enqueue_post( $post_id, $platforms ? $platforms : null, $force, $options );
		}
		if ( ! $platforms ) {
			return new WP_Error( 'forwp_social_no_platforms', __( 'Select at least one platform.', '4wp-social' ), array( 'status' => 400 ) );
		}

		return $service->enqueue_manual( $post_id, $platforms, $options, $force );
	}

	/**
	 * Put a failed job back in the queue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function retry( WP_REST_Request $request ) {
		$repo = new Job_Repository();
		$job  = $repo->find( (int) $request['id'] );
		if ( ! $job ) {
			return new WP_Error( 'forwp_social_no_job', __( 'Job not found.', '4wp-social' ), array( 'status' => 404 ) );
		}

		$repo->update(
			(int) $job->id,
			array(
				'status'     => Job_Repository::QUEUED,
				'attempts'   => 0,
				'reference'  => '',
				'started_at' => null,
				'message'    => '',
				'run_at'     => current_time( 'mysql', true ),
			)
		);

		return new WP_REST_Response( self::shape( $repo->find( (int) $job->id ) ) );
	}

	/**
	 * Public shape of a job row.
	 *
	 * @param object|null $row Row.
	 * @return array<string, mixed>
	 */
	public static function shape( $row ): array {
		if ( ! $row ) {
			return array();
		}

		return array(
			'id'         => (int) $row->id,
			'post_id'    => (int) $row->post_id,
			'platform'   => (string) $row->platform,
			'source'     => (string) $row->source,
			'status'     => (string) $row->status,
			'attempts'   => (int) $row->attempts,
			'remote_id'  => (string) $row->remote_id,
			'remote_url' => (string) $row->remote_url,
			'message'    => (string) $row->message,
			'run_at'     => (string) $row->run_at,
			'updated_at' => (string) $row->updated_at,
		);
	}
}
