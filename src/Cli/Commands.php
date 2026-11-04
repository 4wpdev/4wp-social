<?php
/**
 * WP-CLI: wp forwp-social reindex | migrate-seo.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Cli;

use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Seo_Migration;
use ForWP\Social\Modules\Posting\Status_Index;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Cross-posting maintenance.
 */
final class Commands {

	/**
	 * Build the status index of existing posts (reads meta.json through 4WP Drive).
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Only this post.
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public function reindex( $args, $assoc ): void {
		unset( $args );
		$ids = isset( $assoc['post'] ) ? array( (int) $assoc['post'] ) : get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI maintenance.
					'relation' => 'OR',
					array( 'key' => Package_Reader::DRIVE_PACKAGE_META ),
					array( 'key' => '_forwp_social_published' ),
				),
			)
		);

		$failed = 0;
		foreach ( $ids as $post_id ) {
			$index   = Status_Index::rebuild( (int) $post_id );
			$package = ( new Package_Reader() )->package( (int) $post_id );
			if ( is_wp_error( $package ) ) {
				++$failed;
				WP_CLI::warning( sprintf( '#%d: meta.json was not read, manual platforms are missing: %s', (int) $post_id, $package->get_error_message() ) );
			}
			WP_CLI::log( sprintf( '#%d: %d platform(s)', (int) $post_id, count( $index ) ) );
		}
		if ( $failed ) {
			WP_CLI::error( sprintf( '%d of %d post(s) were not read from meta.json. Fix the access and run again.', $failed, count( $ids ) ) );
		}
		WP_CLI::success( sprintf( '%d post(s) indexed.', count( $ids ) ) );
	}

	/**
	 * Copy cross-posting data from 4WP SEO Helper. The source data stays untouched.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show the report, change nothing.
	 *
	 * @subcommand migrate-seo
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public function migrate_seo( $args, $assoc ): void {
		unset( $args );
		$report = Seo_Migration::run( ! empty( $assoc['dry-run'] ) );

		foreach ( $report['details'] as $row ) {
			WP_CLI::log( sprintf( '#%d  add: %s  keep: %s', $row['post_id'], self::names( $row['added'] ), self::names( $row['kept'] ) ) );
		}
		WP_CLI::log( 'Platforms: ' . self::names( $report['platforms'] ) );
		if ( $report['platforms_no_key'] ) {
			WP_CLI::warning( 'Platforms skipped, no key: ' . implode( ', ', $report['platforms_no_key'] ) );
		}
		WP_CLI::log( 'Manual delay, minutes: ' . ( $report['delay_minutes'] ?? 'not set, default stays' ) );

		$summary = sprintf( '%d post(s), %d publication(s) to add, %d already known.', $report['posts'], $report['added'], $report['kept'] );
		WP_CLI::success( ( $report['dry_run'] ? 'Dry run, nothing changed: ' : '' ) . $summary );
	}

	/**
	 * Comma list, or a dash when empty.
	 *
	 * @param array<int, string> $names Names.
	 */
	private static function names( array $names ): string {
		return $names ? implode( ', ', $names ) : '—';
	}
}
