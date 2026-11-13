<?php
/**
 * Analytics: sources, snapshots, parts, date range.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Csv;
use ForWP\Social\Modules\Analytics\Sources\Planned_Source;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \ForWP\Social\Modules\Analytics\Analytics
 * @covers \ForWP\Social\Modules\Analytics\Sources\Planned_Source
 * @covers \ForWP\Social\Modules\Analytics\Csv
 */
class AnalyticsTest extends TestCase {

	protected function setUp(): void {
		forwp_social_tests_reset();
	}

	public function test_snapshot_is_saved_per_source(): void {
		Analytics::save( 'youtube.com', array( 'channel' => array( 'views' => 5 ) ), 1000 );
		Analytics::save( 'tiktok.com', array( 'user' => array() ), 2000 );

		$this->assertSame( 1000, Analytics::snapshot( 'youtube.com' )['fetched_at'] );
		$this->assertSame( 5, Analytics::snapshot( 'youtube.com' )['data']['channel']['views'] );
		$this->assertCount( 2, Analytics::snapshots() );
		$this->assertNull( Analytics::snapshot( 'instagram.com' ) );
	}

	public function test_part_keeps_data_and_wraps_errors(): void {
		$this->assertSame( array( 'a' => 1 ), Analytics::part( array( 'a' => 1 ) ) );
		$this->assertSame( array( 'error' => 'Denied' ), Analytics::part( new WP_Error( 'x', 'Denied' ) ) );
	}

	public function test_range_ends_yesterday(): void {
		$now = gmmktime( 12, 0, 0, 11, 13, 2026 );

		$this->assertSame( array( '2026-10-16', '2026-11-12' ), Analytics::range( 28, $now ) );
	}

	public function test_planned_source_never_fetches(): void {
		$source = new Planned_Source( 'linkedin.com', 'LinkedIn', 'Manual upload.' );

		$this->assertSame( Analytics::PLANNED, $source->status() );
		$this->assertInstanceOf( WP_Error::class, $source->fetch() );
	}

	public function test_csv_rows_are_keyed_by_header(): void {
		$rows = Csv::parse( "\xEF\xBB\xBFDate;Impressions;Clicks\r\n11/01/2026;120;7\r\n\r\n11/02/2026;98;\n" );

		$this->assertCount( 2, $rows );
		$this->assertSame( array( 'Date' => '11/01/2026', 'Impressions' => '120', 'Clicks' => '7' ), $rows[0] );
		$this->assertSame( '', $rows[1]['Clicks'] );
	}

	public function test_import_rejects_wrong_extension(): void {
		$result = Analytics::import( 'linkedin.com', __FILE__, 'export.xlsx' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertNull( Analytics::snapshot( 'linkedin.com' ) );
	}

	public function test_import_keeps_other_parts_of_the_snapshot(): void {
		$file = tempnam( sys_get_temp_dir(), 'li' );
		file_put_contents( $file, "Date,Impressions\n11/01/2026,120\n" );
		Analytics::save( 'linkedin.com', array( 'note' => 'kept' ), 1000 );

		$data = Analytics::import( 'linkedin.com', $file, 'export.csv' );
		unlink( $file );

		$this->assertSame( 'kept', $data['note'] );
		$this->assertSame( 'export.csv', $data[ Analytics::IMPORT_KEY ]['file'] );
		$this->assertSame( '120', Analytics::snapshot( 'linkedin.com' )['data'][ Analytics::IMPORT_KEY ]['rows'][0]['Impressions'] );
	}
}
