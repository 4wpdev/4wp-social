<?php
/**
 * Metrics store: ranges, totals, deltas; YouTube daily rows.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Analytics\Metrics_Store;
use ForWP\Social\Modules\Analytics\Sources\Facebook_Source;
use ForWP\Social\Modules\Analytics\Sources\Google_Analytics_Source;
use ForWP\Social\Modules\Analytics\Sources\Instagram_Source;
use ForWP\Social\Modules\Analytics\Sources\YouTube_Source;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Analytics\Metrics_Store
 * @covers \ForWP\Social\Modules\Analytics\Sources\YouTube_Source::daily_rows
 * @covers \ForWP\Social\Modules\Analytics\Sources\Facebook_Source::daily_rows
 * @covers \ForWP\Social\Modules\Analytics\Sources\Google_Analytics_Source::daily_rows
 * @covers \ForWP\Social\Modules\Analytics\Sources\Instagram_Source::daily_rows
 */
class MetricsStoreTest extends TestCase {

	private function now(): int {
		return gmmktime( 12, 0, 0, 11, 22, 2026 );
	}

	public function test_presets_end_yesterday(): void {
		$this->assertSame( array( 'preset' => '7d', 'start' => '2026-11-15', 'end' => '2026-11-21' ), Metrics_Store::resolve_range( '7d', '', '', $this->now() ) );
		$this->assertSame( array( 'preset' => 'month', 'start' => '2026-11-01', 'end' => '2026-11-21' ), Metrics_Store::resolve_range( 'month', '', '', $this->now() ) );
		$this->assertSame( array( 'preset' => 'prev_month', 'start' => '2026-10-01', 'end' => '2026-10-31' ), Metrics_Store::resolve_range( 'prev_month', '', '', $this->now() ) );
	}

	public function test_unknown_preset_falls_back_to_default(): void {
		$this->assertSame( '28d', Metrics_Store::resolve_range( 'bogus', '', '', $this->now() )['preset'] );
		$this->assertSame( '28d', Metrics_Store::resolve_range( 'custom', 'x', '', $this->now() )['preset'] );
	}

	public function test_custom_range_is_ordered_and_capped(): void {
		$range = Metrics_Store::resolve_range( 'custom', '2026-12-05', '2026-11-01', $this->now() );

		$this->assertSame( array( 'preset' => 'custom', 'start' => '2026-11-01', 'end' => '2026-11-21' ), $range );
	}

	public function test_previous_range_has_same_length(): void {
		$this->assertSame( array( '2026-10-18', '2026-10-24' ), Metrics_Store::previous_range( '2026-10-25', '2026-10-31' ) );
		$this->assertCount( 7, Metrics_Store::days( '2026-10-25', '2026-10-31' ) );
	}

	public function test_collect_range_history_then_rewrite(): void {
		$this->assertSame( array( '2025-11-22', '2026-11-21' ), Metrics_Store::collect_range( null, 365, $this->now() ) );
		$this->assertSame( array( '2026-11-13', '2026-11-21' ), Metrics_Store::collect_range( '2026-11-20', 365, $this->now() ) );
	}

	public function test_totals_sum_and_weighted_average(): void {
		$metrics = array(
			'views'               => array( 'agg' => 'sum' ),
			'averageViewDuration' => array(
				'agg'    => 'avg',
				'weight' => 'views',
			),
		);
		$rows    = array(
			'2026-11-01' => array(
				'views'               => 10.0,
				'averageViewDuration' => 30.0,
			),
			'2026-11-02' => array(
				'views'               => 30.0,
				'averageViewDuration' => 10.0,
			),
		);

		$this->assertSame( array( 'views' => 40.0, 'averageViewDuration' => 15.0 ), Metrics_Store::totals( $rows, $metrics ) );
		$this->assertSame( array( 'views' => null, 'averageViewDuration' => null ), Metrics_Store::totals( array(), $metrics ) );
	}

	public function test_delta(): void {
		$this->assertSame( 50.0, Metrics_Store::delta( 15.0, 10.0 ) );
		$this->assertSame( -50.0, Metrics_Store::delta( 5.0, 10.0 ) );
		$this->assertNull( Metrics_Store::delta( 5.0, 0.0 ) );
		$this->assertNull( Metrics_Store::delta( 5.0, null ) );
	}

	public function test_youtube_daily_rows_add_net_subscribers(): void {
		$report = array(
			'columnHeaders' => array( array( 'name' => 'day' ), array( 'name' => 'views' ), array( 'name' => 'subscribersGained' ), array( 'name' => 'subscribersLost' ) ),
			'rows'          => array( array( '2026-11-01', 120, 3, 1 ) ),
		);

		$this->assertSame(
			array(
				'2026-11-01' => array(
					'views'             => 120.0,
					'subscribersGained' => 3.0,
					'subscribersLost'   => 1.0,
					'subscribersNet'    => 2.0,
				),
			),
			YouTube_Source::daily_rows( $report )
		);
	}

	public function test_cumulative_delta_sums_item_changes(): void {
		$prev_items = array(
			'a' => array( 'views' => 100.0, 'likes' => 5.0 ),
			'b' => array( 'views' => 10.0, 'likes' => 1.0 ),
		);
		$items      = array(
			'a' => array( 'views' => 130.0, 'likes' => 6.0 ),
			'b' => array( 'views' => 10.0, 'likes' => 1.0 ),
			'c' => array( 'views' => 7.0, 'likes' => 2.0 ),
		);

		$delta = Metrics_Store::cumulative_delta( array( 'followers' => 20.0, 'videos' => 60.0 ), $prev_items, array( 'followers' => 22.0, 'videos' => 61.0 ), $items );

		$this->assertSame( array( 'followers' => 2.0, 'videos' => 1.0, 'views' => 37.0, 'likes' => 3.0 ), $delta );
	}

	public function test_item_deltas_never_negative(): void {
		$this->assertSame( array( 'a' => array( 'views' => 0.0 ) ), Metrics_Store::item_deltas( array( 'a' => array( 'views' => 50.0 ) ), array( 'a' => array( 'views' => 40.0 ) ) ) );
	}

	public function test_facebook_daily_rows_day_before_end_time(): void {
		$res = array(
			'data' => array(
				array(
					'name'   => 'page_media_view',
					'values' => array( array( 'value' => 12, 'end_time' => '2026-11-21T08:00:00+0000' ) ),
				),
				array(
					'name'   => 'page_daily_follows_unique',
					'values' => array( array( 'value' => 2, 'end_time' => '2026-11-21T08:00:00+0000' ) ),
				),
				array(
					'name'   => 'unknown_metric',
					'values' => array( array( 'value' => 5, 'end_time' => '2026-11-21T08:00:00+0000' ) ),
				),
			),
		);

		$this->assertSame( array( '2026-11-20' => array( 'views' => 12.0, 'follows' => 2.0 ) ), Facebook_Source::daily_rows( $res ) );
	}

	public function test_instagram_daily_rows_reach_only(): void {
		$res = array(
			'data' => array(
				array(
					'name'   => 'reach',
					'values' => array( array( 'value' => 7, 'end_time' => '2026-11-21T08:00:00+0000' ) ),
				),
				array(
					'name'   => 'views',
					'values' => array( array( 'value' => 9, 'end_time' => '2026-11-21T08:00:00+0000' ) ),
				),
			),
		);

		$this->assertSame( array( '2026-11-20' => array( 'reach' => 7.0 ) ), Instagram_Source::daily_rows( $res ) );
	}

	public function test_ga4_daily_rows_add_engagement(): void {
		$report = array(
			'metricHeaders' => array( array( 'name' => 'activeUsers' ), array( 'name' => 'sessions' ), array( 'name' => 'userEngagementDuration' ), array( 'name' => 'engagedSessions' ) ),
			'rows'          => array(
				array(
					'dimensionValues' => array( array( 'value' => '20261120' ) ),
					'metricValues'    => array( array( 'value' => '10' ), array( 'value' => '20' ), array( 'value' => '600' ), array( 'value' => '5' ) ),
				),
			),
		);

		$row = Google_Analytics_Source::daily_rows( $report )['2026-11-20'];

		$this->assertSame( 60.0, $row['avgEngagement'] );
		$this->assertSame( 25.0, $row['engagementRate'] );
	}
}
