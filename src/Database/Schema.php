<?php
/**
 * Database schema: publishing queue, log, daily analytics metrics.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades custom tables.
 */
final class Schema {

	/**
	 * Jobs table name without prefix.
	 */
	public const JOBS_TABLE = 'forwp_social_jobs';

	/**
	 * Bump when custom tables change so existing installs run dbDelta.
	 */
	public const DB_VERSION = 3;

	public const DB_VERSION_OPTION = 'forwp_social_db_version';

	/**
	 * Plugin activation: create tables and schedule the queue.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::maybe_upgrade();
		\ForWP\Social\Modules\Posting\Queue::schedule();
	}

	/**
	 * Create or upgrade tables when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}

		self::create_jobs_table();
		self::create_metrics_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Jobs table.
	 *
	 * @return void
	 */
	public static function create_jobs_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::JOBS_TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			platform varchar(64) NOT NULL,
			source varchar(20) NOT NULL DEFAULT 'package',
			options longtext,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			reference varchar(191) NOT NULL DEFAULT '',
			remote_id varchar(191) NOT NULL DEFAULT '',
			remote_url varchar(255) NOT NULL DEFAULT '',
			message text,
			started_at datetime DEFAULT NULL,
			run_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_platform (post_id, platform),
			KEY status_run (status, run_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Daily analytics metrics table.
	 *
	 * @return void
	 */
	public static function create_metrics_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( \ForWP\Social\Modules\Analytics\Metrics_Store::schema( $wpdb->get_charset_collate() ) );
	}

	/**
	 * Full table name.
	 */
	public static function jobs_table(): string {
		global $wpdb;

		return $wpdb->prefix . self::JOBS_TABLE;
	}
}
