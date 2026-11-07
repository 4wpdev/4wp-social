<?php
/**
 * Calendar events from the local index and the queue. Planner reads them through REST or the filter (ADR 0003).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use DateTimeImmutable;
use DateTimeZone;
use ForWP\Social\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One event = post × platform × day.
 */
final class Calendar {

	/**
	 * Status => statistics panel.
	 */
	public const STAT_GROUPS = array(
		Job_Repository::PUBLISHED    => 'published',
		Status_Index::PLANNED        => 'scheduled',
		Job_Repository::QUEUED       => 'scheduled',
		Job_Repository::PROCESSING   => 'scheduled',
		Status_Index::MANUAL_PENDING => 'waiting',
		Job_Repository::FAILED       => 'failed',
	);

	/**
	 * Valid YYYY-MM, or the current month.
	 *
	 * @param string $month Month.
	 */
	public static function month( string $month ): string {
		return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ? $month : wp_date( 'Y-m' );
	}

	/**
	 * Date of an index entry: run_at while planned or queued, updated_at for the rest.
	 *
	 * @param array<string, string> $entry Index entry.
	 */
	public static function event_time( array $entry ): string {
		$status = (string) ( $entry['status'] ?? '' );
		if ( in_array( $status, array( Status_Index::PLANNED, Job_Repository::QUEUED ), true ) ) {
			return (string) ( $entry['run_at'] ?? '' );
		}

		return (string) ( $entry['updated_at'] ?? '' );
	}

	/**
	 * Events of one month. Pure. A plan without a date is not on the calendar. Index rows win over job rows of the same day.
	 *
	 * @param array<int, array{post_id: int, platform: string, entry: array<string, string>}> $rows  Index rows, then job rows.
	 * @param string                                                                          $month YYYY-MM.
	 * @param DateTimeZone                                                                    $zone  Site time zone.
	 * @return array<int, array{date: string, post_id: int, platform: string, status: string, mode: string, url: string}>
	 */
	public static function events_from_rows( array $rows, string $month, DateTimeZone $zone ): array {
		$events = array();
		foreach ( $rows as $row ) {
			$time = self::event_time( $row['entry'] );
			$unix = '' !== $time ? strtotime( $time ) : false;
			if ( false === $unix ) {
				continue;
			}

			$local = ( new DateTimeImmutable( '@' . $unix ) )->setTimezone( $zone );
			$date  = $local->format( 'Y-m-d' );
			if ( 0 !== strpos( $date, $month . '-' ) ) {
				continue;
			}

			$key = $row['post_id'] . '|' . $row['platform'] . '|' . $date;
			if ( isset( $events[ $key ] ) ) {
				continue;
			}
			$events[ $key ] = array(
				'date'     => $date,
				'time'     => $local->format( 'H:i' ),
				'post_id'  => (int) $row['post_id'],
				'platform' => (string) $row['platform'],
				'status'   => (string) ( $row['entry']['status'] ?? '' ),
				'mode'     => (string) ( $row['entry']['mode'] ?? '' ),
				'url'      => (string) ( $row['entry']['url'] ?? '' ),
			);
		}

		$events = array_values( $events );
		usort(
			$events,
			static function ( array $a, array $b ): int {
				return array( $a['date'], $a['post_id'] ) <=> array( $b['date'], $b['post_id'] );
			}
		);

		return $events;
	}

	/**
	 * Events per state. Pure.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 * @return array{published: int, scheduled: int, waiting: int, failed: int}
	 */
	public static function stats( array $events ): array {
		$stats = array(
			'published' => 0,
			'scheduled' => 0,
			'waiting'   => 0,
			'failed'    => 0,
		);
		foreach ( $events as $event ) {
			$group = self::STAT_GROUPS[ (string) ( $event['status'] ?? '' ) ] ?? '';
			if ( '' !== $group ) {
				++$stats[ $group ];
			}
		}

		return $stats;
	}

	/**
	 * Published and scheduled per platform. Pure. Every enabled platform is listed, zero included; others only when they have some.
	 *
	 * @param array<int, array<string, mixed>>    $events    Events.
	 * @param array<string, array<string, mixed>> $platforms Registry rows in display order.
	 * @return array<string, array{published: int, scheduled: int}>
	 */
	public static function platform_counts( array $events, array $platforms ): array {
		$zero   = array(
			'published' => 0,
			'scheduled' => 0,
		);
		$counts = array();
		foreach ( $platforms as $key => $platform ) {
			if ( ! empty( $platform['enabled'] ) ) {
				$counts[ (string) $key ] = $zero;
			}
		}
		foreach ( $events as $event ) {
			$group = self::STAT_GROUPS[ (string) ( $event['status'] ?? '' ) ] ?? '';
			if ( isset( $zero[ $group ] ) ) {
				$key                       = (string) $event['platform'];
				$counts[ $key ]            = $counts[ $key ] ?? $zero;
				$counts[ $key ][ $group ] += 1;
			}
		}

		return $counts;
	}

	/**
	 * Site content of a month: one row for the publication, one for the last update on another day. Pure.
	 * WordPress keeps only the last modified date, so earlier updates are not known.
	 *
	 * @param array<int, array{post_id: int, post_type: string, post_date: string, post_modified: string}> $posts Posts, dates in site time.
	 * @param string                                                                                       $month YYYY-MM.
	 * @return array<int, array{date: string, time: string, post_id: int, post_type: string, action: string}>
	 */
	public static function site_rows( array $posts, string $month ): array {
		$rows = array();
		foreach ( $posts as $post ) {
			$moments = array( 'publish' => $post['post_date'] );
			if ( substr( $post['post_modified'], 0, 10 ) > substr( $post['post_date'], 0, 10 ) ) {
				$moments['update'] = $post['post_modified'];
			}
			foreach ( $moments as $action => $moment ) {
				if ( 0 === strpos( $moment, $month . '-' ) ) {
					$rows[] = array(
						'date'      => substr( $moment, 0, 10 ),
						'time'      => substr( $moment, 11, 5 ),
						'post_id'   => (int) $post['post_id'],
						'post_type' => $post['post_type'],
						'action'    => $action,
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * Post types shown as site content: Page, Post, Video, Case first, then the other public types.
	 *
	 * @return array<int, string>
	 */
	public static function site_post_types(): array {
		$public = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$first  = array_intersect( array( 'page', 'post', (string) Settings::all()['video']['post_type'], 'practice_case' ), $public );
		$types  = array_values( array_unique( array_merge( $first, $public ) ) );

		/**
		 * Filter post types of the site row in the calendar, in display order.
		 *
		 * @param array<int, string> $types Post types.
		 */
		$filtered = apply_filters( 'forwp_social_calendar_site_post_types', $types );

		return is_array( $filtered ) ? array_values( array_map( 'strval', $filtered ) ) : $types;
	}

	/**
	 * What was published and updated on the site in a month, in display order.
	 *
	 * @param string $month YYYY-MM.
	 * @return array<int, array<string, mixed>>
	 */
	public static function site_events( string $month ): array {
		$month = self::month( $month );
		$first = $month . '-01 00:00:00';
		$last  = gmdate( 'Y-m-t 23:59:59', (int) strtotime( $month . '-01 UTC' ) );
		$types = self::site_post_types();
		if ( ! $types ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'date_query'             => array(
					'relation' => 'OR',
					array(
						'column'    => 'post_date',
						'after'     => $first,
						'before'    => $last,
						'inclusive' => true,
					),
					array(
						'column'    => 'post_modified',
						'after'     => $first,
						'before'    => $last,
						'inclusive' => true,
					),
				),
			)
		);

		$posts = array();
		foreach ( $found as $post ) {
			$posts[ $post->ID ] = array(
				'post_id'       => (int) $post->ID,
				'post_type'     => (string) $post->post_type,
				'post_date'     => (string) $post->post_date,
				'post_modified' => (string) $post->post_modified,
			);
		}

		$order = array_flip( $types );
		$rows  = self::site_rows( array_values( $posts ), $month );
		usort(
			$rows,
			static function ( array $a, array $b ) use ( $order ): int {
				return array( $a['date'], $order[ $a['post_type'] ] ?? 999, $a['time'] ) <=> array( $b['date'], $order[ $b['post_type'] ] ?? 999, $b['time'] );
			}
		);

		foreach ( $rows as &$row ) {
			$object            = get_post_type_object( $row['post_type'] );
			$row['type_label'] = $object ? (string) $object->labels->singular_name : $row['post_type'];
			$row['icon']       = self::type_icon( $row['post_type'], $object && is_string( $object->menu_icon ) ? $object->menu_icon : '' );
			$row['title']      = self::title( $row['post_id'] );
			$row['edit_url']   = (string) get_edit_post_link( $row['post_id'], 'raw' );
			$row['url']        = (string) get_permalink( $row['post_id'] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Post title as plain text: callers escape it, and scripts must not show "&#8217;".
	 *
	 * @param int $post_id Post id.
	 */
	private static function title( int $post_id ): string {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Dashicon of a post type.
	 *
	 * @param string $type Post type.
	 * @param string $icon Menu icon of the type.
	 */
	private static function type_icon( string $type, string $icon ): string {
		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			return $icon;
		}

		return 'page' === $type ? 'dashicons-admin-page' : 'dashicons-admin-post';
	}

	/**
	 * Planned and running work, soonest first. Pure. Undated plans wait for a decision, so they are not here.
	 *
	 * @param array<int, array{post_id: int, platform: string, entry: array<string, string>}> $rows     Index rows, then job rows.
	 * @param string                                                                          $platform Only this platform ('' = all).
	 * @param int                                                                             $limit    Max items.
	 * @return array<int, array{post_id: int, platform: string, status: string, mode: string, run_at: string}>
	 */
	public static function scheduled_from_rows( array $rows, string $platform, int $limit ): array {
		$active = array( Status_Index::PLANNED, Job_Repository::QUEUED, Job_Repository::PROCESSING );
		$items  = array();
		foreach ( $rows as $row ) {
			$status = (string) ( $row['entry']['status'] ?? '' );
			$run_at = (string) ( $row['entry']['run_at'] ?? '' );
			$key    = $row['post_id'] . '|' . $row['platform'];
			if ( '' === $run_at || isset( $items[ $key ] ) || ! in_array( $status, $active, true ) || ( '' !== $platform && $platform !== $row['platform'] ) ) {
				continue;
			}
			$items[ $key ] = array(
				'post_id'  => (int) $row['post_id'],
				'platform' => (string) $row['platform'],
				'status'   => $status,
				'mode'     => (string) ( $row['entry']['mode'] ?? '' ),
				'run_at'   => $run_at,
			);
		}

		$items = array_values( $items );
		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strtotime( $a['run_at'] ) <=> strtotime( $b['run_at'] );
			}
		);

		return array_slice( $items, 0, $limit );
	}

	/**
	 * Planned and running work with post titles and edit links.
	 *
	 * @param string $platform Only this platform ('' = all).
	 * @param int    $limit    Max items.
	 * @return array<int, array<string, mixed>>
	 */
	public static function scheduled( string $platform = '', int $limit = 50 ): array {
		$rows = self::index_rows();
		foreach ( ( new Job_Repository() )->active() as $job ) {
			$rows[] = array(
				'post_id'  => (int) $job->post_id,
				'platform' => (string) $job->platform,
				'entry'    => Status_Index::fields_from_job( $job ),
			);
		}

		$rows  = array_filter( $rows, static fn( array $row ): bool => (bool) get_post( $row['post_id'] ) );
		$items = self::scheduled_from_rows( array_values( $rows ), $platform, $limit );
		foreach ( $items as &$item ) {
			$item['title']    = self::title( $item['post_id'] );
			$item['edit_url'] = (string) get_edit_post_link( $item['post_id'], 'raw' );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Every platform of every post in the local index.
	 *
	 * @return array<int, array{post_id: int, platform: string, entry: array<string, string>}>
	 */
	private static function index_rows(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => Status_Index::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only posts with statuses.
			)
		);

		$rows = array();
		foreach ( $ids as $post_id ) {
			foreach ( Status_Index::get( (int) $post_id ) as $platform => $entry ) {
				$rows[] = array(
					'post_id'  => (int) $post_id,
					'platform' => (string) $platform,
					'entry'    => $entry,
				);
			}
		}

		return $rows;
	}

	/**
	 * Publications from the published map and the SEO Helper log. Local post meta, read only.
	 *
	 * @return array<int, array{post_id: int, platform: string, entry: array<string, string>}>
	 */
	private static function done_rows(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin screen.
					'relation' => 'OR',
					array( 'key' => Posting_Service::PUBLISHED_META ),
					array( 'key' => Seo_Migration::LOG_META ),
				),
			)
		);

		$rows = array();
		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			foreach ( ( new Posting_Service() )->published_map( $post_id ) as $platform => $row ) {
				$rows[] = self::done_row( $post_id, (string) $platform, (string) ( $row['at'] ?? '' ), (string) ( $row['url'] ?? '' ), Status_Index::MODE_API );
			}
			foreach ( Seo_Migration::latest_from_log( get_post_meta( $post_id, Seo_Migration::LOG_META, true ) ) as $platform => $row ) {
				$rows[] = self::done_row( $post_id, (string) $platform, $row['posted_at'], $row['url'], Status_Index::MODE_MANUAL );
			}
		}

		return $rows;
	}

	/**
	 * One publication as a calendar row.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 * @param string $at       Date.
	 * @param string $url      Remote URL.
	 * @param string $mode     Mode.
	 * @return array{post_id: int, platform: string, entry: array<string, string>}
	 */
	private static function done_row( int $post_id, string $platform, string $at, string $url, string $mode ): array {
		return array(
			'post_id'  => $post_id,
			'platform' => $platform,
			'entry'    => array(
				'status'     => Job_Repository::PUBLISHED,
				'mode'       => $mode,
				'url'        => $url,
				'updated_at' => $at,
			),
		);
	}

	/**
	 * Series taxonomies shown per day, in priority order (4WP Planner uses the same list).
	 *
	 * @return string[]
	 */
	public static function series_taxonomies(): array {
		$list = apply_filters( 'forwp_social_series_taxonomies', array( 'video-series', 'category' ) );

		return array_values( array_filter( (array) $list, 'taxonomy_exists' ) );
	}

	/**
	 * Series terms per day from posts with publications (done or planned) and site content of that day.
	 * A post in several terms adds each of them.
	 *
	 * @param array<int, array<string, mixed>> $events Calendar events.
	 * @param array<int, array<string, mixed>> $site   Site content rows.
	 * @return array<string, array<int, array{taxonomy: string, id: int, name: string}>>
	 */
	public static function day_terms( array $events, array $site = array() ): array {
		$taxonomies = self::series_taxonomies();
		$default    = (int) get_option( 'default_category' );
		$by_day     = array();

		foreach ( array_merge( $events, $site ) as $row ) {
			$date    = (string) ( $row['date'] ?? '' );
			$post_id = (int) ( $row['post_id'] ?? $row['id'] ?? 0 );
			if ( '' === $date || $post_id <= 0 ) {
				continue;
			}
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( 'category' === $taxonomy && (int) $term->term_id === $default ) {
						continue;
					}
					$by_day[ $date ][ $taxonomy . ':' . $term->term_id ] = array(
						'taxonomy' => $taxonomy,
						'id'       => (int) $term->term_id,
						'name'     => $term->name,
					);
				}
			}
		}

		return array_map( 'array_values', $by_day );
	}

	/**
	 * Events of a month with post titles and edit links.
	 *
	 * @param string $month YYYY-MM.
	 * @return array<int, array<string, mixed>>
	 */
	public static function events( string $month ): array {
		$month = self::month( $month );
		$zone  = wp_timezone();
		$first = new DateTimeImmutable( $month . '-01 00:00:00', $zone );
		$utc   = new DateTimeZone( 'UTC' );
		$rows  = self::index_rows();

		$jobs = ( new Job_Repository() )->between(
			$first->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$first->modify( '+1 month' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' )
		);
		foreach ( $jobs as $job ) {
			$rows[] = array(
				'post_id'  => (int) $job->post_id,
				'platform' => (string) $job->platform,
				'entry'    => Status_Index::fields_from_job( $job ) + array( 'updated_at' => gmdate( 'c', (int) strtotime( $job->updated_at . ' UTC' ) ) ),
			);
		}

		// Done work the index does not hold yet: linked copies and manual posts logged by 4WP SEO Helper.
		$rows = array_merge( $rows, self::done_rows() );

		// Jobs can outlive their post.
		$events = array_values(
			array_filter(
				self::events_from_rows( $rows, $month, $zone ),
				static fn( array $event ): bool => (bool) get_post( $event['post_id'] )
			)
		);
		foreach ( $events as &$event ) {
			$event['title']    = self::title( $event['post_id'] );
			$event['edit_url'] = (string) get_edit_post_link( $event['post_id'], 'raw' );
		}
		unset( $event );

		/**
		 * Filter calendar events (4WP Planner adds or reads them here).
		 *
		 * @param array<int, array<string, mixed>> $events Events.
		 * @param string                           $month  YYYY-MM.
		 */
		$filtered = apply_filters( 'forwp_social_calendar_events', $events, $month );

		return is_array( $filtered ) ? $filtered : $events;
	}
}
