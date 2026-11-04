<?php
/**
 * CRUD for the publishing queue.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * One row = one post on one platform.
 */
final class Job_Repository {

	public const QUEUED     = 'queued';
	public const PROCESSING = 'processing';
	public const PUBLISHED  = 'published';
	public const FAILED     = 'failed';

	/**
	 * Job sources: meta.json package (Drive / MCP) or the post itself (button, MCP).
	 */
	public const SOURCE_PACKAGE = 'package';
	public const SOURCE_POST    = 'post';

	/**
	 * Add a job.
	 *
	 * @param int                  $post_id  Post id.
	 * @param string               $platform Platform key.
	 * @param int                  $run_at   Unix time to run.
	 * @param string               $source   SOURCE_PACKAGE | SOURCE_POST.
	 * @param array<string, mixed> $options  Source options (attachment ids, privacy, texts).
	 * @return int Job id (0 on failure).
	 */
	public function create( int $post_id, string $platform, int $run_at, string $source = self::SOURCE_PACKAGE, array $options = array() ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
		$ok = $wpdb->insert(
			Schema::jobs_table(),
			array(
				'post_id'    => $post_id,
				'platform'   => $platform,
				'source'     => $source,
				'options'    => (string) wp_json_encode( $options ),
				'status'     => self::QUEUED,
				'attempts'   => 0,
				'message'    => '',
				'run_at'     => gmdate( 'Y-m-d H:i:s', $run_at ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return 0;
		}

		$id  = (int) $wpdb->insert_id;
		$job = $this->find( $id );
		if ( $job ) {
			Status_Index::from_job( $job );
		}

		return $id;
	}

	/**
	 * Update a job.
	 *
	 * @param int                  $id     Job id.
	 * @param array<string, mixed> $fields Columns.
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;

		$fields['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update( Schema::jobs_table(), $fields, array( 'id' => $id ) );

		$job = $this->find( $id );
		if ( $job ) {
			Status_Index::from_job( $job );
		}
	}

	/**
	 * @param int $id Job id.
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ? $row : null;
	}

	/**
	 * Decoded options of a job row.
	 *
	 * @param object $job Job row.
	 * @return array<string, mixed>
	 */
	public static function options( object $job ): array {
		$data = json_decode( (string) ( $job->options ?? '' ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Latest job for a post on a platform.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 */
	public function latest( int $post_id, string $platform ): ?object {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d AND platform = %s ORDER BY id DESC LIMIT 1", $post_id, $platform ) );

		return $row ? $row : null;
	}

	/**
	 * Run the queued jobs of a post now (it was just published).
	 *
	 * @param int $post_id Post id.
	 */
	public function wake( int $post_id ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::jobs_table(),
			array(
				'run_at'     => $now,
				'updated_at' => $now,
			),
			array(
				'post_id' => $post_id,
				'status'  => self::QUEUED,
			)
		);

		foreach ( $this->recent( $post_id, 50 ) as $job ) {
			if ( self::QUEUED === $job->status ) {
				Status_Index::from_job( $job );
			}
		}
	}

	/**
	 * Jobs still waiting or running, whatever their date.
	 *
	 * @return array<int, object>
	 */
	public function active(): array {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN (%s, %s) ORDER BY run_at ASC", self::QUEUED, self::PROCESSING ) );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Jobs touched in a period, for the calendar.
	 *
	 * @param string $from UTC datetime.
	 * @param string $to   UTC datetime.
	 * @return array<int, object>
	 */
	public function between( string $from, string $to ): array {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE updated_at BETWEEN %s AND %s OR run_at BETWEEN %s AND %s ORDER BY id DESC", $from, $to, $from, $to ) );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Jobs due now (queued or processing), oldest first.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public function due( int $limit ): array {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN (%s, %s) AND run_at <= %s ORDER BY run_at ASC, id ASC LIMIT %d",
				self::QUEUED,
				self::PROCESSING,
				current_time( 'mysql', true ),
				$limit
			)
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Recent jobs for the log screen / REST.
	 *
	 * @param int $post_id Filter by post (0 = all).
	 * @param int $limit   Max rows.
	 * @return array<int, object>
	 */
	public function recent( int $post_id = 0, int $limit = 50 ): array {
		global $wpdb;

		$table = Schema::jobs_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
		if ( $post_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT %d", $post_id, $limit ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
		}
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}
}
