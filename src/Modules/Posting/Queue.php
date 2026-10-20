<?php
/**
 * WP-Cron worker for the publishing queue.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

defined( 'ABSPATH' ) || exit;

/**
 * Runs due jobs every minute while the queue is not empty.
 */
final class Queue {

	public const HOOK = 'forwp_social_process_queue';

	private const SCHEDULE = 'forwp_social_minute';

	private const LOCK = 'forwp_social_queue_lock';

	/**
	 * Jobs per run: uploads are slow, keep a run well under max_execution_time.
	 */
	private const BATCH = 2;

	/**
	 * Register the 1-minute interval. Called from Plugin::boot so activation can schedule it.
	 *
	 * @return void
	 */
	public static function register_interval(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Queue poll.
	}

	/**
	 * Attach the worker (Posting module enabled).
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
		self::schedule();
	}

	/**
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_interval( array $schedules ): array {
		if ( ! isset( $schedules[ self::SCHEDULE ] ) ) {
			$schedules[ self::SCHEDULE ] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every minute (4WP Social queue)', '4wp-social' ),
			);
		}

		return $schedules;
	}

	/**
	 * (Re)schedule the worker.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}
		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		try {
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Large uploads.
			}

			$service = new Posting_Service();
			foreach ( ( new Job_Repository() )->due( self::BATCH ) as $job ) {
				$service->run( $job );
			}
		} finally {
			delete_transient( self::LOCK );
		}
	}
}
