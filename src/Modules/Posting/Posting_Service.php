<?php
/**
 * Enqueue, run and finalize publishing jobs.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Contracts\Media_Source_Interface;
use ForWP\Social\Core\Publish_Result;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic of the Posting module (no hooks here).
 */
final class Posting_Service {

	/**
	 * Post meta: {platform: {id, url, at}}.
	 */
	public const PUBLISHED_META = '_forwp_social_published';

	public const MAX_ATTEMPTS = 3;

	/**
	 * Give up polling after this many seconds.
	 */
	private const PROCESSING_TIMEOUT = 2 * HOUR_IN_SECONDS;

	/**
	 * Seconds between status polls.
	 */
	private const POLL_INTERVAL = MINUTE_IN_SECONDS;

	/**
	 * Seconds between checks while a queued post is still a draft.
	 */
	private const WAIT_INTERVAL = 15 * MINUTE_IN_SECONDS;

	/**
	 * @var Job_Repository
	 */
	private $jobs;

	/**
	 * @var Package_Reader
	 */
	private $reader;

	/**
	 * @var Media_Source_Interface
	 */
	private $post_reader;

	/**
	 * @param Job_Repository|null         $jobs        Repository.
	 * @param Package_Reader|null         $reader      meta.json source.
	 * @param Media_Source_Interface|null $post_reader Post source.
	 */
	public function __construct( ?Job_Repository $jobs = null, ?Package_Reader $reader = null, ?Media_Source_Interface $post_reader = null ) {
		$this->jobs        = $jobs ? $jobs : new Job_Repository();
		$this->reader      = $reader ? $reader : new Package_Reader();
		$this->post_reader = $post_reader ? $post_reader : new Post_Reader();
	}

	/**
	 * Queue platforms from the post itself (editor button, MCP). Runs on the next cron tick.
	 *
	 * @param int                  $post_id   Post id.
	 * @param array<int, string>   $platforms Platform keys.
	 * @param array<string, mixed> $options   Post_Reader::sanitize() input.
	 * @param bool                 $force     Queue again even if already posted.
	 * @return array<string, array{job_id: int, skipped: string}>
	 */
	public function enqueue_manual( int $post_id, array $platforms, array $options = array(), bool $force = false ): array {
		$options = Post_Reader::sanitize( $options );
		$done    = $this->published_map( $post_id );
		$report  = array();

		foreach ( $platforms as $platform ) {
			$platform = (string) $platform;
			$reason   = '';

			if ( ! Adapter_Registry::get( $platform ) ) {
				$reason = 'no_adapter';
			} elseif ( ! $force && isset( $done[ $platform ] ) ) {
				$reason = 'already_posted';
			} elseif ( $this->has_active_job( $post_id, $platform ) ) {
				$reason = 'in_queue';
			}

			$report[ $platform ] = array(
				'job_id'  => '' === $reason ? $this->jobs->create( $post_id, $platform, time(), Job_Repository::SOURCE_POST, $options ) : 0,
				'skipped' => $reason,
			);
		}

		Queue::schedule();

		return $report;
	}

	/**
	 * Queue every api-mode platform of a post that is not posted yet.
	 *
	 * @param int                     $post_id   Post id.
	 * @param array<int, string>|null $platforms Limit to these keys (null = all api platforms).
	 * @param bool                    $force     Queue again even if already posted.
	 * @param array<string, mixed>    $options   Overrides (privacy).
	 * @return array<string, array{job_id: int, skipped: string}>|WP_Error
	 */
	public function enqueue_post( int $post_id, ?array $platforms = null, bool $force = false, array $options = array() ) {
		$package = $this->reader->package( $post_id, true );
		if ( is_wp_error( $package ) ) {
			return $package;
		}
		if ( ! is_array( $package ) ) {
			return new WP_Error( 'forwp_social_no_package', __( 'This post has no meta.json package (or 4WP Drive is inactive).', '4wp-social' ) );
		}

		// Drive does not fire forwp_drive_document_imported on "update existing post": catch up here.
		$importer = new Package_Importer();
		if ( ! $importer->is_current( $post_id, $package ) ) {
			$imported = $importer->import( $post_id );
			if ( is_wp_error( $imported ) ) {
				return $imported;
			}
		}

		$keys   = null === $platforms ? Package_Reader::api_platforms( $package ) : $platforms;
		$run_at = time() + ( $force ? 0 : Package_Reader::delay_seconds( $package ) );
		$done   = $this->published_map( $post_id );
		$report = array();

		foreach ( $keys as $platform ) {
			$entry  = $package['platforms'][ $platform ] ?? null;
			$reason = '';

			if ( ! is_array( $entry ) ) {
				$reason = 'not_declared';
			} elseif ( ! Adapter_Registry::get( $platform ) ) {
				$reason = 'no_adapter';
			} elseif ( ! $force && ( isset( $done[ $platform ] ) || 'posted' === ( $entry['status'] ?? '' ) ) ) {
				$reason = 'already_posted';
			} elseif ( $this->has_active_job( $post_id, $platform ) ) {
				$reason = 'in_queue';
			}

			$job_id = '' === $reason ? $this->jobs->create( $post_id, $platform, $run_at, Job_Repository::SOURCE_PACKAGE, array_intersect_key( Post_Reader::sanitize( $options ), array( 'privacy' => true ) ) ) : 0;

			$report[ $platform ] = array(
				'job_id'  => $job_id,
				'skipped' => $reason,
			);
		}

		if ( ! empty( array_filter( wp_list_pluck( $report, 'job_id' ) ) ) ) {
			Queue::schedule();
		}

		return $report;
	}

	/**
	 * Run one due job: publish (queued) or poll (processing).
	 *
	 * @param object $job Job row.
	 * @return void
	 */
	public function run( object $job ): void {
		$job_id   = (int) $job->id;
		$platform = (string) $job->platform;
		$post_id  = (int) $job->post_id;
		$adapter  = Adapter_Registry::get( $platform );

		if ( ! $adapter ) {
			$this->fail( $job, Publish_Result::failed( __( 'No adapter is registered for this platform.', '4wp-social' ), false ) );
			return;
		}

		$polling = Job_Repository::PROCESSING === $job->status && '' !== (string) $job->reference;

		$started = (string) ( $job->started_at ?? '' );
		if ( $polling && '' !== $started && strtotime( $started . ' UTC' ) < time() - self::PROCESSING_TIMEOUT ) {
			$this->fail( $job, Publish_Result::failed( __( 'The platform did not finish processing in time.', '4wp-social' ), false ) );
			return;
		}

		// The button may be pressed on a draft or a scheduled post: the copy goes out when the original is live.
		if ( ! $polling && 'publish' !== get_post_status( $post_id ) ) {
			$this->wait_for_publish( $job_id, $post_id );
			return;
		}

		$source  = Job_Repository::SOURCE_POST === (string) ( $job->source ?? '' ) ? $this->post_reader : $this->reader;
		$package = $source->build( $post_id, $platform, ! $polling, Job_Repository::options( $job ) );
		if ( is_wp_error( $package ) ) {
			$this->fail( $job, Publish_Result::failed( $package->get_error_message() ) );
			return;
		}

		if ( ! $polling ) {
			$this->jobs->update( $job_id, array( 'started_at' => current_time( 'mysql', true ) ) );
		}

		try {
			$result = $polling ? $adapter->check( (string) $job->reference, $package ) : $adapter->publish( $package );
		} catch ( \Throwable $e ) {
			$result = Publish_Result::failed( $e->getMessage() );
		} finally {
			$package->cleanup();
		}

		if ( $result->is_published() ) {
			$this->finalize( $job, $result );
			return;
		}

		if ( $result->is_processing() ) {
			$this->jobs->update(
				$job_id,
				array(
					'status'    => Job_Repository::PROCESSING,
					'reference' => '' !== $result->reference() ? $result->reference() : (string) $job->reference,
					'message'   => $result->message(),
					'run_at'    => gmdate( 'Y-m-d H:i:s', time() + self::POLL_INTERVAL ),
				)
			);
			return;
		}

		$this->fail( $job, $result );
	}

	/**
	 * Keep the job queued until the post is published (scheduled: its date; draft: check again later).
	 *
	 * @param int $job_id  Job id.
	 * @param int $post_id Post id.
	 * @return void
	 */
	private function wait_for_publish( int $job_id, int $post_id ): void {
		$future = 'future' === get_post_status( $post_id ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
		$run_at = max( $future, time() + self::WAIT_INTERVAL );

		$this->jobs->update(
			$job_id,
			array(
				'message' => $future
					/* translators: %s: publish date. */
					? sprintf( __( 'Waiting for the post to be published (%s).', '4wp-social' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $future ) )
					: __( 'Waiting for the post to be published.', '4wp-social' ),
				'run_at'  => gmdate( 'Y-m-d H:i:s', $run_at ),
			)
		);
	}

	/**
	 * Mark published, remember the id on the post and in meta.json.
	 *
	 * @param object         $job    Job row.
	 * @param Publish_Result $result Result.
	 * @return void
	 */
	private function finalize( object $job, Publish_Result $result ): void {
		$post_id   = (int) $job->post_id;
		$platform  = (string) $job->platform;
		$posted_at = gmdate( 'c' );
		$message   = $result->message();

		$map              = $this->published_map( $post_id );
		$map[ $platform ] = array(
			'id'  => $result->remote_id(),
			'url' => $result->remote_url(),
			'at'  => $posted_at,
		);
		update_post_meta( $post_id, self::PUBLISHED_META, $map );

		$synced = apply_filters(
			'forwp_drive_update_package',
			null,
			$post_id,
			array(
				'platforms' => array(
					$platform => array(
						'status'    => 'posted',
						'url'       => $result->remote_url(),
						'id'        => $result->remote_id(),
						'posted_at' => $posted_at,
						'via'       => '4wp-social',
					),
				),
			)
		);
		if ( is_wp_error( $synced ) ) {
			$message = trim( $message . ' meta.json: ' . $synced->get_error_message() );
		}

		$this->jobs->update(
			(int) $job->id,
			array(
				'status'     => Job_Repository::PUBLISHED,
				'remote_id'  => $result->remote_id(),
				'remote_url' => $result->remote_url(),
				'message'    => $message,
			)
		);

		/**
		 * Fires after a post was published on a platform.
		 *
		 * @param int            $post_id  Post id.
		 * @param string         $platform Platform key.
		 * @param Publish_Result $result   Result.
		 */
		do_action( 'forwp_social_published', $post_id, $platform, $result );
	}

	/**
	 * Retry with backoff or give up.
	 *
	 * @param object         $job    Job row.
	 * @param Publish_Result $result Failed result.
	 * @return void
	 */
	private function fail( object $job, Publish_Result $result ): void {
		$attempts = (int) $job->attempts + 1;
		$polling  = Job_Repository::PROCESSING === $job->status && '' !== (string) $job->reference;

		if ( $result->is_retryable() && $attempts < self::MAX_ATTEMPTS ) {
			$this->jobs->update(
				(int) $job->id,
				array(
					'status'   => $polling ? Job_Repository::PROCESSING : Job_Repository::QUEUED,
					'attempts' => $attempts,
					'message'  => $result->message(),
					'run_at'   => gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS * $attempts ),
				)
			);
			return;
		}

		$this->jobs->update(
			(int) $job->id,
			array(
				'status'   => Job_Repository::FAILED,
				'attempts' => $attempts,
				'message'  => $result->message(),
			)
		);

		/**
		 * Fires when a job gives up (hook for 4WP Notifications).
		 *
		 * @param int    $post_id  Post id.
		 * @param string $platform Platform key.
		 * @param string $message  Error text.
		 */
		do_action( 'forwp_social_failed', (int) $job->post_id, (string) $job->platform, $result->message() );
	}

	/**
	 * @param int $post_id Post id.
	 * @return array<string, array{id: string, url: string, at: string}>
	 */
	public function published_map( int $post_id ): array {
		$map = get_post_meta( $post_id, self::PUBLISHED_META, true );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 */
	private function has_active_job( int $post_id, string $platform ): bool {
		$latest = $this->jobs->latest( $post_id, $platform );

		return $latest && in_array( $latest->status, array( Job_Repository::QUEUED, Job_Repository::PROCESSING ), true );
	}
}
