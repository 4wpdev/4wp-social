<?php
/**
 * One-way copy of the cross-posting data of 4WP SEO Helper. Source data is never changed.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Core\Settings;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Safe to run again: a result already known to Social is kept.
 */
final class Seo_Migration {

	public const LOG_META         = '_forwp_seo_crosspost_log';
	private const NOTIFIED_META   = '_forwp_seo_crosspost_notified_at';
	private const NOTIFY_ERR_META = '_forwp_seo_crosspost_notify_error';
	private const TAXONOMY        = 'forwp_platform';
	private const OPT_DELAY_HOURS = 'forwp_seo_crossposting_delay_hours';
	private const OPT_DELAY_MINS  = 'forwp_seo_crossposting_delay_minutes';

	/**
	 * Last publication per platform. Pure.
	 *
	 * @param mixed $log Raw log meta.
	 * @return array<string, array{url: string, slug: string, posted_at: string}>
	 */
	public static function latest_from_log( $log ): array {
		$out = array();
		foreach ( is_array( $log ) ? $log : array() as $row ) {
			$key = is_array( $row ) ? strtolower( trim( (string) ( $row['platform'] ?? '' ) ) ) : '';
			if ( '' === $key ) {
				continue;
			}
			$out[ $key ] = array(
				'url'       => (string) ( $row['url'] ?? '' ),
				'slug'      => (string) ( $row['slug'] ?? '' ),
				'posted_at' => (string) ( $row['posted_at'] ?? '' ),
			);
		}

		return $out;
	}

	/**
	 * Apply a log to the index and the published map. Pure.
	 *
	 * @param array<string, array<string, string>> $index  Status index.
	 * @param array<string, array<string, string>> $map    Published map.
	 * @param array<string, array<string, string>> $latest latest_from_log().
	 * @return array{index: array<string, array<string, string>>, map: array<string, array<string, string>>, added: array<int, string>, kept: array<int, string>}
	 */
	public static function merge_log( array $index, array $map, array $latest ): array {
		$added = array();
		$kept  = array();
		foreach ( $latest as $key => $row ) {
			$known = $index[ $key ] ?? array();
			if ( Job_Repository::PUBLISHED === ( $known['status'] ?? '' ) ) {
				$kept[] = $key;
			} else {
				$at            = '' !== $row['posted_at'] ? $row['posted_at'] : gmdate( 'c' );
				$index[ $key ] = array_merge(
					Status_Index::merge_entry( $known, array( 'mode' => $known['mode'] ?? Status_Index::MODE_MANUAL ) ),
					array(
						'status'     => Job_Repository::PUBLISHED,
						'url'        => $row['url'],
						'remote_id'  => $row['slug'],
						'updated_at' => $at,
						'via'        => 'migration',
					)
				);
				$added[]       = $key;
			}

			// Without this the queue could publish the same copy again.
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array(
					'id'  => $row['slug'],
					'url' => $row['url'],
					'at'  => '' !== $row['posted_at'] ? $row['posted_at'] : gmdate( 'c' ),
					'via' => 'migration',
				);
			}
		}

		return array(
			'index' => $index,
			'map'   => $map,
			'added' => $added,
			'kept'  => $kept,
		);
	}

	/**
	 * Registry rows from the taxonomy, skipped terms apart.
	 *
	 * @return array{rows: array<int, array<string, mixed>>, skipped: array<int, string>}
	 */
	public static function registry_rows(): array {
		$out = array(
			'rows'    => array(),
			'skipped' => array(),
		);
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return $out;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$key = (string) get_term_meta( $term->term_id, 'forwp_platform_key', true );
			if ( '' === $key ) {
				$out['skipped'][] = $term->name;
				continue;
			}
			$url           = (string) get_term_meta( $term->term_id, 'forwp_platform_url', true );
			$order         = get_term_meta( $term->term_id, 'forwp_platform_order', true );
			$out['rows'][] = array(
				'key'     => strtolower( $key ),
				'label'   => $term->name,
				'url'     => $url,
				'format'  => (string) get_term_meta( $term->term_id, 'forwp_platform_format', true ),
				'enabled' => '0' !== (string) get_term_meta( $term->term_id, 'forwp_platform_enabled', true ),
				'order'   => '' !== (string) $order ? (int) $order + 100 : 200,
			);
		}

		return $out;
	}

	/**
	 * Manual delay in minutes from the SEO Helper options, null when they were never saved.
	 */
	public static function delay_minutes(): ?int {
		$hours   = get_option( self::OPT_DELAY_HOURS, '' );
		$minutes = get_option( self::OPT_DELAY_MINS, '' );
		if ( '' === (string) $hours && '' === (string) $minutes ) {
			return null;
		}

		return min( 10080, max( 0, (int) $hours ) * 60 + max( 0, (int) $minutes ) );
	}

	/**
	 * Run or preview the migration.
	 *
	 * @param bool $dry_run Change nothing.
	 * @return array<string, mixed> Report.
	 */
	public static function run( bool $dry_run ): array {
		$registry = self::registry_rows();
		$delay    = self::delay_minutes();
		$report   = array(
			'dry_run'          => $dry_run,
			'posts'            => 0,
			'added'            => 0,
			'kept'             => 0,
			'details'          => array(),
			'platforms'        => wp_list_pluck( $registry['rows'], 'key' ),
			'platforms_no_key' => $registry['skipped'],
			'delay_minutes'    => $delay,
		);

		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::LOG_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
			)
		);

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			$merged  = self::merge_log(
				Status_Index::get( $post_id ),
				( new Posting_Service() )->published_map( $post_id ),
				self::latest_from_log( get_post_meta( $post_id, self::LOG_META, true ) )
			);

			++$report['posts'];
			$report['added']    += count( $merged['added'] );
			$report['kept']     += count( $merged['kept'] );
			$report['details'][] = array(
				'post_id' => $post_id,
				'added'   => $merged['added'],
				'kept'    => $merged['kept'],
			);

			if ( ! $dry_run ) {
				$index = self::with_notify_fields( $post_id, $merged['index'] );
				update_post_meta( $post_id, Status_Index::META, $index );
				update_post_meta( $post_id, Posting_Service::PUBLISHED_META, $merged['map'] );
			}
		}

		if ( ! $dry_run ) {
			// Connector bindings stay: the taxonomy knows nothing about them.
			Platform_Registry::save( Platform_Registry::merge_rows( Platform_Registry::all( false ), $registry['rows'] ) );

			if ( null !== $delay ) {
				update_option( Settings::OPTION_KEY, array_merge( Settings::all(), array( 'manual_delay_minutes' => $delay ) ), false );
			}
			update_option( 'forwp_social_seo_migrated_at', gmdate( 'c' ), false );
		}

		return $report;
	}

	/**
	 * Reminder result of a post goes onto its manual platforms.
	 *
	 * @param int                                  $post_id Post id.
	 * @param array<string, array<string, string>> $index   Index.
	 * @return array<string, array<string, string>>
	 */
	private static function with_notify_fields( int $post_id, array $index ): array {
		$fields = array_filter(
			array(
				'notified_at'  => (string) get_post_meta( $post_id, self::NOTIFIED_META, true ),
				'notify_error' => (string) get_post_meta( $post_id, self::NOTIFY_ERR_META, true ),
			)
		);
		foreach ( $index as $key => $entry ) {
			if ( Status_Index::MODE_MANUAL === ( $entry['mode'] ?? '' ) ) {
				$index[ $key ] = array_merge( $entry, $fields );
			}
		}

		return $index;
	}
}
