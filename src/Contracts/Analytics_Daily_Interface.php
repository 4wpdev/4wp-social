<?php
/**
 * Analytics source with daily totals: feeds the shared analytics panel.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Daily rows for the metrics table, metric definitions and items (videos, posts) per period.
 */
interface Analytics_Daily_Interface {

	/**
	 * Daily totals for a period.
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @return array<string, array<string, float>>|WP_Error Day (Y-m-d) => metric => value.
	 */
	public function daily( string $start, string $end );

	/**
	 * Metric definitions, in display order.
	 * `agg`: sum | avg (weighted by `weight`, a metric key); `format`: num | time (seconds); `chart`: true for the chart metric.
	 *
	 * @return array<string, array{label: string, agg: string, weight?: string, format?: string, chart?: bool}>
	 */
	public function metrics(): array;

	/**
	 * Days to load on the first run.
	 */
	public function history_days(): int;

	/**
	 * Items (videos, posts) of a period, each: id, title, url, thumbnail, duration, published_at + metric values.
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @param int    $limit Max items.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function items( string $start, string $end, int $limit );

	/**
	 * Table columns for items: metric key => { label, format: num | time | hours }.
	 *
	 * @return array<string, array{label: string, format: string}>
	 */
	public function item_columns(): array;
}
