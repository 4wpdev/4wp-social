<?php
/**
 * Daily metrics table, period ranges and totals.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics;

use ForWP\Social\Contracts\Analytics_Cumulative_Interface;
use ForWP\Social\Contracts\Analytics_Daily_Interface;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One row per platform + day + item (empty item = account total).
 */
final class Metrics_Store {

	public const TABLE = 'forwp_social_metrics';

	/**
	 * Days rewritten on every run: platforms finish their numbers late.
	 */
	public const REWRITE_DAYS = 7;

	/**
	 * Range presets: key => days (0 = calendar month).
	 */
	public const PRESETS = array(
		'7d'         => 7,
		'28d'        => 28,
		'90d'        => 90,
		'365d'       => 365,
		'month'      => 0,
		'prev_month' => 0,
	);

	public const DEFAULT_PRESET = '28d';

	public const ITEMS_VERSION = 'forwp_social_items_version';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Table SQL for dbDelta.
	 *
	 * @param string $charset Charset collate.
	 */
	public static function schema( string $charset ): string {
		$table = self::table();

		return "CREATE TABLE {$table} (
			platform varchar(64) NOT NULL,
			day date NOT NULL,
			item varchar(191) NOT NULL DEFAULT '',
			metrics longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (platform, day, item)
		) {$charset};";
	}

	/**
	 * Insert or replace daily rows of one platform.
	 *
	 * @param string                              $platform Platform slug.
	 * @param array<string, array<string, float>> $days     Day => metrics.
	 * @param string                              $item     Item id ('' = account).
	 */
	public static function upsert( string $platform, array $days, string $item = '' ): void {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $days as $day => $metrics ) {
			if ( ! self::is_day( (string) $day ) ) {
				continue;
			}
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
				self::table(),
				array(
					'platform'   => $platform,
					'day'        => $day,
					'item'       => $item,
					'metrics'    => (string) wp_json_encode( $metrics ),
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Daily rows of a period, ordered by day.
	 *
	 * @param string $platform Platform slug.
	 * @param string $start    First day.
	 * @param string $end      Last day.
	 * @return array<string, array<string, float>>
	 */
	public static function rows( string $platform, string $start, string $end ): array {
		global $wpdb;

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table, fresh data.
		$raw = $wpdb->get_results( $wpdb->prepare( "SELECT day, metrics FROM {$table} WHERE platform = %s AND item = '' AND day BETWEEN %s AND %s ORDER BY day ASC", $platform, $start, $end ), ARRAY_A );

		$out = array();
		foreach ( (array) $raw as $row ) {
			$metrics                     = json_decode( (string) $row['metrics'], true );
			$out[ (string) $row['day'] ] = is_array( $metrics ) ? array_map( 'floatval', $metrics ) : array();
		}

		return $out;
	}

	/**
	 * Latest stored day of a platform, or null.
	 *
	 * @param string $platform Platform slug.
	 */
	public static function latest_day( string $platform ): ?string {
		global $wpdb;

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$day = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(day) FROM {$table} WHERE platform = %s AND item = ''", $platform ) );

		return is_string( $day ) && self::is_day( $day ) ? $day : null;
	}

	/**
	 * Load daily rows from the source: history on the first run, then the last REWRITE_DAYS.
	 *
	 * @param string                    $slug   Platform slug.
	 * @param Analytics_Daily_Interface $source Source.
	 * @param int                       $now    Timestamp.
	 * @param array<string, mixed>      $raw    fetch() answer (lifetime-counter sources build their snapshot from it).
	 * @return int|WP_Error Rows written.
	 */
	public static function collect( string $slug, Analytics_Daily_Interface $source, int $now, array $raw = array() ) {
		if ( $source instanceof Analytics_Cumulative_Interface ) {
			// Account history from the API (history_days > 1): snapshots only feed items.
			$from_api = $source->history_days() > 1;
			$snap     = $source->snapshot( $raw );
			if ( null === $snap && ! $from_api ) {
				return new WP_Error( 'forwp_social_metrics_snapshot', __( 'No counters to store: the platform answered with an error.', '4wp-social' ) );
			}
			$written = null === $snap ? 0 : self::collect_cumulative( $slug, $snap, $now, ! $from_api );
			update_option( self::ITEMS_VERSION, (string) $now, false );
			if ( ! $from_api ) {
				return $written;
			}
		}

		list( $start, $end ) = self::collect_range( self::latest_day( $slug ), $source->history_days(), $now );

		$days = $source->daily( $start, $end );
		if ( is_wp_error( $days ) ) {
			return $days;
		}

		self::upsert( $slug, $days );
		update_option( self::ITEMS_VERSION, (string) $now, false );

		return count( $days );
	}

	/**
	 * Store today's lifetime counters (platform `slug:raw`) and the change since the previous snapshot as yesterday's row.
	 *
	 * @param string                                                                                                                      $slug Platform slug.
	 * @param array{account: array<string, float>, items: array<string, array<string, float>>, meta: array<string, array<string, mixed>>} $snap Snapshot.
	 * @param int                                                                                                                         $now  Timestamp.
	 * @param bool                                                                                                                        $delta Write the account change as yesterday's row (false: the source gives account history itself).
	 * @return int Rows written.
	 */
	public static function collect_cumulative( string $slug, array $snap, int $now, bool $delta = true ): int {
		global $wpdb;

		$raw   = self::raw_key( $slug );
		$today = gmdate( 'Y-m-d', $now );
		$table = self::table();

		// Previous snapshot day (before today).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$prev_day = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(day) FROM {$table} WHERE platform = %s AND item = '' AND day < %s", $raw, $today ) );

		self::upsert( $raw, array( $today => $snap['account'] ) );
		foreach ( $snap['items'] as $id => $counters ) {
			self::upsert( $raw, array( $today => $counters ), (string) $id );
		}
		$meta = get_option( self::meta_key( $slug ), array() );
		update_option( self::meta_key( $slug ), array_merge( is_array( $meta ) ? $meta : array(), $snap['meta'] ), false );

		if ( ! $delta || ! is_string( $prev_day ) || ! self::is_day( $prev_day ) ) {
			return 0; // First snapshot: nothing to compare yet.
		}

		$prev      = self::snapshot_at( $raw, $prev_day );
		$delta     = self::cumulative_delta( $prev['account'], $prev['items'], $snap['account'], $snap['items'] );
		$yesterday = gmdate( 'Y-m-d', $now - DAY_IN_SECONDS );
		self::upsert( $slug, array( $yesterday => $delta ) );

		return 1;
	}

	/**
	 * Change between two snapshots: account counters by difference, item counters summed over items.
	 * An item missing in the previous snapshot counts from zero.
	 *
	 * @param array<string, float>                $prev_account Previous account counters.
	 * @param array<string, array<string, float>> $prev_items   Previous item counters.
	 * @param array<string, float>                $account      Current account counters.
	 * @param array<string, array<string, float>> $items        Current item counters.
	 * @return array<string, float>
	 */
	public static function cumulative_delta( array $prev_account, array $prev_items, array $account, array $items ): array {
		$out = array();
		foreach ( $account as $key => $value ) {
			$out[ $key ] = (float) $value - (float) ( $prev_account[ $key ] ?? $value );
		}
		foreach ( self::item_deltas( $prev_items, $items ) as $counters ) {
			foreach ( $counters as $key => $value ) {
				$out[ $key ] = ( $out[ $key ] ?? 0.0 ) + $value;
			}
		}

		return $out;
	}

	/**
	 * Per-item change: current minus base (missing base = zero). Negative changes (deleted counts) become zero.
	 *
	 * @param array<string, array<string, float>> $base  Base counters.
	 * @param array<string, array<string, float>> $items Current counters.
	 * @return array<string, array<string, float>>
	 */
	public static function item_deltas( array $base, array $items ): array {
		$out = array();
		foreach ( $items as $id => $counters ) {
			foreach ( $counters as $key => $value ) {
				$out[ (string) $id ][ $key ] = max( 0.0, (float) $value - (float) ( $base[ $id ][ $key ] ?? 0 ) );
			}
		}

		return $out;
	}

	/**
	 * Item changes over a period for a lifetime-counter source, joined with item meta.
	 * Base = snapshot taken on the first day (state at its start); top = snapshot of the day after the last day.
	 *
	 * @param string $slug  Platform slug.
	 * @param string $start First day.
	 * @param string $end   Last day.
	 * @return array<int, array<string, mixed>>
	 */
	public static function cumulative_items( string $slug, string $start, string $end ): array {
		global $wpdb;

		$raw   = self::raw_key( $slug );
		$table = self::table();
		$after = gmdate( 'Y-m-d', strtotime( $end . ' 00:00:00 UTC' ) + DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, item, metrics FROM {$table} WHERE platform = %s AND item <> '' AND day <= %s ORDER BY day ASC", $raw, $after ), ARRAY_A );

		$base  = array();
		$first = array();
		$top   = array();
		foreach ( (array) $rows as $row ) {
			$id       = (string) $row['item'];
			$counters = json_decode( (string) $row['metrics'], true );
			$counters = is_array( $counters ) ? array_map( 'floatval', $counters ) : array();
			if ( ! isset( $first[ $id ] ) ) {
				$first[ $id ] = $counters;
			}
			if ( $row['day'] <= $start ) {
				$base[ $id ] = $counters;
			}
			$top[ $id ] = $counters;
		}

		$meta    = get_option( self::meta_key( $slug ), array() );
		$meta    = is_array( $meta ) ? $meta : array();
		$history = (bool) $base; // Snapshots reach back to the period start.
		$out     = array();
		foreach ( $top as $id => $counters ) {
			$info = is_array( $meta[ $id ] ?? null ) ? $meta[ $id ] : array();
			if ( ! isset( $base[ $id ] ) && ! $history ) {
				// History starts inside the period: count from the first snapshot, unless the item was published in the period.
				$published   = ! empty( $info['published_at'] ) ? gmdate( 'Y-m-d', (int) strtotime( (string) $info['published_at'] ) ) : '';
				$base[ $id ] = '' !== $published && $published >= $start ? array() : $first[ $id ];
			}
			$delta = self::item_deltas( array( $id => $base[ $id ] ?? array() ), array( $id => $counters ) )[ $id ] ?? array();
			if ( ! array_sum( $delta ) && ( $info['published_at'] ?? '' ) < $start ) {
				continue; // No activity in the period.
			}
			$out[] = array_merge( $info, $delta, array( 'id' => $id ) );
		}

		return $out;
	}

	/**
	 * Account and item counters of one snapshot day.
	 *
	 * @param string $raw Raw platform key.
	 * @param string $day Day.
	 * @return array{account: array<string, float>, items: array<string, array<string, float>>}
	 */
	private static function snapshot_at( string $raw, string $day ): array {
		global $wpdb;

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT item, metrics FROM {$table} WHERE platform = %s AND day = %s", $raw, $day ), ARRAY_A );

		$out = array(
			'account' => array(),
			'items'   => array(),
		);
		foreach ( (array) $rows as $row ) {
			$counters = json_decode( (string) $row['metrics'], true );
			$counters = is_array( $counters ) ? array_map( 'floatval', $counters ) : array();
			if ( '' === (string) $row['item'] ) {
				$out['account'] = $counters;
			} else {
				$out['items'][ (string) $row['item'] ] = $counters;
			}
		}

		return $out;
	}

	/**
	 * Platform key of lifetime counters.
	 *
	 * @param string $slug Platform slug.
	 */
	private static function raw_key( string $slug ): string {
		return $slug . ':raw';
	}

	/**
	 * Option with item meta (title, url, thumbnail …) of a lifetime-counter source.
	 *
	 * @param string $slug Platform slug.
	 */
	private static function meta_key( string $slug ): string {
		return 'forwp_social_items_meta_' . str_replace( array( '.', '-' ), '_', $slug );
	}

	/**
	 * Days to load: from the latest stored day minus REWRITE_DAYS, or the full history; ends yesterday.
	 *
	 * @param string|null $latest  Latest stored day.
	 * @param int         $history History days.
	 * @param int         $now     Timestamp.
	 * @return array{0: string, 1: string}
	 */
	public static function collect_range( ?string $latest, int $history, int $now ): array {
		$end   = $now - DAY_IN_SECONDS;
		$first = $end - ( max( 1, $history ) - 1 ) * DAY_IN_SECONDS;
		$start = null === $latest ? $first : max( $first, strtotime( $latest . ' 00:00:00 UTC' ) - self::REWRITE_DAYS * DAY_IN_SECONDS );

		return array( gmdate( 'Y-m-d', min( $start, $end ) ), gmdate( 'Y-m-d', $end ) );
	}

	/**
	 * Items of a period, cached for a day (cache drops after every collect).
	 *
	 * @param string                    $slug   Platform slug.
	 * @param Analytics_Daily_Interface $source Source.
	 * @param string                    $start  First day.
	 * @param string                    $end    Last day.
	 * @param int                       $limit  Max items.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function items( string $slug, Analytics_Daily_Interface $source, string $start, string $end, int $limit ) {
		$key    = 'forwp_social_items_' . md5( implode( '|', array( $slug, $start, $end, $limit, (string) get_option( self::ITEMS_VERSION, '' ) ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$items = $source->items( $start, $end, $limit );
		if ( ! is_wp_error( $items ) ) {
			set_transient( $key, $items, DAY_IN_SECONDS );
		}

		return $items;
	}

	/**
	 * Period from a preset or custom dates. Presets end yesterday.
	 *
	 * @param string $preset Preset key or "custom".
	 * @param string $from   Custom first day.
	 * @param string $to     Custom last day.
	 * @param int    $now    Timestamp.
	 * @return array{preset: string, start: string, end: string}
	 */
	public static function resolve_range( string $preset, string $from, string $to, int $now ): array {
		$yesterday = gmdate( 'Y-m-d', $now - DAY_IN_SECONDS );

		if ( 'custom' === $preset && self::is_day( $from ) && self::is_day( $to ) ) {
			$start = min( $from, $to );
			$end   = min( max( $from, $to ), $yesterday );

			return array(
				'preset' => 'custom',
				'start'  => min( $start, $end ),
				'end'    => $end,
			);
		}

		if ( 'month' === $preset ) {
			return array(
				'preset' => 'month',
				'start'  => min( gmdate( 'Y-m-01', $now ), $yesterday ),
				'end'    => $yesterday,
			);
		}
		if ( 'prev_month' === $preset ) {
			$first = strtotime( gmdate( 'Y-m-01', $now ) . ' 00:00:00 UTC' );

			return array(
				'preset' => 'prev_month',
				'start'  => gmdate( 'Y-m-01', $first - DAY_IN_SECONDS ),
				'end'    => gmdate( 'Y-m-d', $first - DAY_IN_SECONDS ),
			);
		}

		$preset              = isset( self::PRESETS[ $preset ] ) && self::PRESETS[ $preset ] > 0 ? $preset : self::DEFAULT_PRESET;
		list( $start, $end ) = Analytics::range( self::PRESETS[ $preset ], $now );

		return array(
			'preset' => $preset,
			'start'  => $start,
			'end'    => $end,
		);
	}

	/**
	 * The period of the same length right before.
	 *
	 * @param string $start First day.
	 * @param string $end   Last day.
	 * @return array{0: string, 1: string}
	 */
	public static function previous_range( string $start, string $end ): array {
		$s    = strtotime( $start . ' 00:00:00 UTC' );
		$e    = strtotime( $end . ' 00:00:00 UTC' );
		$days = (int) round( ( $e - $s ) / DAY_IN_SECONDS ) + 1;

		return array( gmdate( 'Y-m-d', $s - $days * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $s - DAY_IN_SECONDS ) );
	}

	/**
	 * Every day of a period (Y-m-d).
	 *
	 * @param string $start First day.
	 * @param string $end   Last day.
	 * @return string[]
	 */
	public static function days( string $start, string $end ): array {
		$out = array();
		for ( $t = strtotime( $start . ' 00:00:00 UTC' ), $e = strtotime( $end . ' 00:00:00 UTC' ); $t <= $e; $t += DAY_IN_SECONDS ) {
			$out[] = gmdate( 'Y-m-d', $t );
		}

		return $out;
	}

	/**
	 * Period totals: sums, weighted averages.
	 *
	 * @param array<string, array<string, float>> $rows    Day => metrics.
	 * @param array<string, array<string, mixed>> $metrics Metric definitions.
	 * @return array<string, float|null> Null when the period has no rows.
	 */
	public static function totals( array $rows, array $metrics ): array {
		$out = array();
		foreach ( $metrics as $key => $def ) {
			if ( ! $rows ) {
				$out[ $key ] = null;
				continue;
			}
			if ( 'avg' === ( $def['agg'] ?? 'sum' ) ) {
				$weight = (string) ( $def['weight'] ?? '' );
				$sum    = 0.0;
				$wsum   = 0.0;
				foreach ( $rows as $row ) {
					$w     = '' !== $weight ? (float) ( $row[ $weight ] ?? 0 ) : 1.0;
					$sum  += (float) ( $row[ $key ] ?? 0 ) * $w;
					$wsum += $w;
				}
				$out[ $key ] = $wsum > 0 ? $sum / $wsum : 0.0;
				continue;
			}
			$out[ $key ] = (float) array_sum( array_column( $rows, $key ) );
		}

		return $out;
	}

	/**
	 * Change in percent, or null when there is nothing to compare.
	 *
	 * @param float|null $current  Current value.
	 * @param float|null $previous Previous value.
	 */
	public static function delta( ?float $current, ?float $previous ): ?float {
		if ( null === $current || null === $previous || 0.0 === $previous ) {
			return null;
		}

		return ( $current - $previous ) / abs( $previous ) * 100;
	}

	/**
	 * Y-m-d check.
	 *
	 * @param string $day Value.
	 */
	public static function is_day( string $day ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day );
	}
}
