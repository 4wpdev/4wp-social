<?php
/**
 * Cross-posting center: status index, registry, calendar, SEO Helper migration.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use DateTimeZone;
use ForWP\Social\Modules\Posting\Adapter_Registry;
use ForWP\Social\Modules\Posting\Calendar;
use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Seo_Migration;
use ForWP\Social\Modules\Posting\Status_Index;
use ForWP\Social\Rest\Rest_Crosspost;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Status_Index
 * @covers \ForWP\Social\Modules\Posting\Platform_Registry
 * @covers \ForWP\Social\Modules\Posting\Calendar
 * @covers \ForWP\Social\Modules\Posting\Seo_Migration
 * @covers \ForWP\Social\Rest\Rest_Crosspost
 */
class CrosspostTest extends TestCase {

	private const NOW = 1800000000;

	protected function setUp(): void {
		forwp_social_tests_reset();
	}

	/**
	 * @param array<string, mixed> $over Overrides.
	 * @return array<string, int|bool>
	 */
	private function context( array $over = array() ): array {
		return array_merge(
			array(
				'live_at'      => self::NOW - 7200,
				'now'          => self::NOW,
				'api_delay'    => 0,
				'manual_delay' => 3600,
				'auto_publish' => false,
			),
			$over
		);
	}

	/**
	 * @param array<string, mixed> $platforms meta.json platforms.
	 * @param array<string, mixed> $current   Current index.
	 * @param array<string, mixed> $context   Context overrides.
	 * @return array<string, array<string, string>>
	 */
	private function merge( array $platforms, array $current = array(), array $context = array() ): array {
		return Status_Index::merge_package( $current, array( 'platforms' => $platforms ), Platform_Registry::all( false ), $this->context( $context ) );
	}

	public function test_manual_platform_waits_only_after_the_delay(): void {
		$index = $this->merge( array( 'x.com' => array( 'status' => 'open' ) ) );
		$this->assertSame( 'manual_pending', $index['x.com']['status'] );
		$this->assertSame( 'manual', $index['x.com']['mode'] );

		$index = $this->merge( array( 'x.com' => array() ), array(), array( 'manual_delay' => 86400 ) );
		$this->assertSame( 'planned', $index['x.com']['status'] );
		$this->assertSame( gmdate( 'c', self::NOW - 7200 + 86400 ), $index['x.com']['run_at'] );
	}

	public function test_draft_has_no_date(): void {
		$index = $this->merge( array( 'x.com' => array() ), array(), array( 'live_at' => 0 ) );
		$this->assertSame( 'planned', $index['x.com']['status'] );
		$this->assertSame( '', $index['x.com']['run_at'] );
	}

	public function test_meta_json_mode_wins_over_registry(): void {
		$index = $this->merge(
			array(
				'dev.to' => array( 'mode' => 'manual' ),
				'x.com'  => array( 'mode' => 'api' ),
			)
		);
		$this->assertSame( 'manual', $index['dev.to']['mode'] );
		$this->assertSame( 'api', $index['x.com']['mode'] );
	}

	public function test_api_plan_has_a_date_only_with_auto_publish(): void {
		$entry = array( 'youtube.com' => array( 'mode' => 'api' ) );
		$this->assertSame( '', $this->merge( $entry )['youtube.com']['run_at'] );
		$this->assertNotSame( '', $this->merge( $entry, array(), array( 'auto_publish' => true ) )['youtube.com']['run_at'] );
	}

	public function test_posted_in_package_is_published(): void {
		$index = $this->merge(
			array(
				'x.com' => array(
					'status'    => 'posted',
					'url'       => 'https://x.com/a/status/1',
					'slug'      => '1',
					'posted_at' => '2026-10-01T10:00:00+00:00',
				),
			)
		);
		$this->assertSame( 'published', $index['x.com']['status'] );
		$this->assertSame( '1', $index['x.com']['remote_id'] );
		$this->assertSame( 'meta.json', $index['x.com']['via'] );
		$this->assertSame( '2026-10-01T10:00:00+00:00', $index['x.com']['updated_at'] );
	}

	public function test_queue_results_are_not_overwritten_by_the_package(): void {
		foreach ( array( 'queued', 'processing', 'failed', 'published' ) as $status ) {
			$current = array(
				'youtube.com' => array(
					'status' => $status,
					'via'    => 'api',
				),
			);
			$index   = $this->merge( array( 'youtube.com' => array( 'mode' => 'api' ) ), $current );
			$this->assertSame( $status, $index['youtube.com']['status'] );
		}
	}

	public function test_skipped_and_disabled_platforms_leave_the_index(): void {
		$index = $this->merge(
			array(
				'x.com'      => array( 'status' => 'skipped' ),
				'reddit.com' => array(),
			)
		);
		$this->assertSame( array(), $index );

		// A publication stays even if the platform is skipped later.
		$current = array( 'x.com' => array( 'status' => 'published' ) );
		$this->assertSame( 'published', $this->merge( array( 'x.com' => array( 'status' => 'skipped' ) ), $current )['x.com']['status'] );
	}

	public function test_plans_gone_from_package_are_removed_results_stay(): void {
		$current = array(
			'x.com'       => array(
				'status' => 'manual_pending',
				'via'    => 'meta.json',
			),
			'youtube.com' => array(
				'status' => 'published',
				'via'    => 'api',
			),
		);
		$index   = $this->merge( array(), $current );
		$this->assertArrayNotHasKey( 'x.com', $index );
		$this->assertArrayHasKey( 'youtube.com', $index );
	}

	public function test_resync_keeps_pending_date_and_reminder_fields(): void {
		$first              = $this->merge( array( 'x.com' => array() ) );
		$first['x.com']['notified_at'] = '2026-01-01T00:00:00+00:00';
		$second             = $this->merge( array( 'x.com' => array() ), $first, array( 'now' => self::NOW + 999 ) );
		$this->assertSame( $first['x.com']['updated_at'], $second['x.com']['updated_at'] );
		$this->assertSame( '2026-01-01T00:00:00+00:00', $second['x.com']['notified_at'] );
	}

	public function test_empty_result_does_not_erase_known_url(): void {
		$entry = Status_Index::merge_entry(
			array( 'url' => 'https://a.test/1' ),
			array(
				'status' => 'queued',
				'url'    => '',
			)
		);
		$this->assertSame( 'https://a.test/1', $entry['url'] );
		$this->assertSame( 'queued', $entry['status'] );
	}

	public function test_job_fields(): void {
		$fields = Status_Index::fields_from_job(
			(object) array(
				'status'     => 'queued',
				'remote_url' => '',
				'remote_id'  => '',
				'run_at'     => '2026-11-04 10:00:00',
			)
		);
		$this->assertSame( '2026-11-04T10:00:00+00:00', $fields['run_at'] );
		$this->assertSame( 'api', $fields['via'] );
	}

	public function test_manual_delay_fallback_does_not_touch_the_api_delay(): void {
		update_option( 'forwp_social_settings', array( 'delay_minutes' => 5 ) );
		$this->assertSame( 300, Package_Reader::delay_seconds( array() ) );
		$this->assertSame( 86400, Package_Reader::delay_seconds( array(), 86400 ) );
		$this->assertSame( 7200, Package_Reader::delay_seconds( array( 'republish' => array( 'delay_hours' => 2 ) ), 86400 ) );
	}

	public function test_registry_starts_from_defaults_and_the_saved_list_is_the_whole_list(): void {
		$rows = Platform_Registry::all( false );
		foreach ( array( 'youtube.com', 'tiktok.com', 'bsky.app', 'dev.to' ) as $key ) {
			$this->assertSame( 'api', $rows[ $key ]['mode'] );
			$this->assertSame( $key, $rows[ $key ]['connector'] );
		}
		$this->assertArrayNotHasKey( 'reddit.com', Platform_Registry::all() );

		Platform_Registry::save( array( array( 'key' => 'REDDIT.com', 'enabled' => '1' ), array( 'key' => 'bad key!' ) ) );
		$this->assertSame( array( 'reddit.com' ), array_keys( Platform_Registry::all( false ) ) );
		$this->assertSame( 'manual', Platform_Registry::get( 'reddit.com' )['mode'] );
	}

	public function test_connector_binds_to_one_platform_only(): void {
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::create( array( 'key' => 'yt2.com', 'connector' => 'youtube.com' ) ) );
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::update( 'x.com', array( 'connector' => 'dev.to' ) ) );

		// Free it, then it can move.
		$this->assertIsArray( Platform_Registry::update( 'dev.to', array( 'connector' => '' ) ) );
		$this->assertSame( 'manual', Platform_Registry::get( 'dev.to' )['mode'] );
		$moved = Platform_Registry::update( 'x.com', array( 'connector' => 'dev.to' ) );
		$this->assertSame( 'api', $moved['mode'] );
		$this->assertSame( 'DEV.to', Adapter_Registry::all()['x.com']->get_label() );
		$this->assertArrayNotHasKey( 'dev.to', Adapter_Registry::all() );
	}

	public function test_saved_rows_cannot_share_a_connector(): void {
		$rows = Platform_Registry::sanitize_rows(
			array(
				array( 'key' => 'a.com', 'connector' => 'dev.to' ),
				array( 'key' => 'b.com', 'connector' => 'dev.to' ),
			)
		);
		$this->assertSame( 'dev.to', $rows['a.com']['connector'] );
		$this->assertSame( '', $rows['b.com']['connector'] );
	}

	public function test_unknown_connector_and_duplicate_key_are_refused(): void {
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::create( array( 'key' => 'new.com', 'connector' => 'nope' ) ) );
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::create( array( 'key' => 'x.com' ) ) );
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::create( array( 'key' => 'bad key!' ) ) );
	}

	public function test_create_delete_reorder(): void {
		$row = Platform_Registry::create( array( 'key' => 'New.com', 'label' => 'New', 'enabled' => true ) );
		$this->assertSame( 'new.com', $row['key'] );
		$this->assertSame( 'new.com', array_key_last( Platform_Registry::all( false ) ) );

		Platform_Registry::reorder( array( 'new.com', 'x.com' ) );
		$this->assertSame( array( 'new.com', 'x.com' ), array_slice( array_keys( Platform_Registry::all( false ) ), 0, 2 ) );

		$this->assertTrue( Platform_Registry::delete( 'new.com' ) );
		$this->assertNull( Platform_Registry::get( 'new.com' ) );
		$this->assertInstanceOf( \WP_Error::class, Platform_Registry::delete( 'new.com' ) );
	}

	public function test_migration_rows_keep_connector_bindings(): void {
		$rows = Platform_Registry::merge_rows(
			Platform_Registry::all( false ),
			array(
				array( 'key' => 'dev.to', 'label' => 'DEV', 'url' => '', 'format' => 'article', 'enabled' => true, 'order' => 101 ),
				array( 'key' => 'threads.net', 'label' => 'Threads', 'url' => '', 'format' => '', 'enabled' => true, 'order' => 110 ),
			)
		);
		$this->assertSame( 'dev.to', $rows['dev.to']['connector'] );
		$this->assertSame( 'https://dev.to/new', $rows['dev.to']['url'] );
		$this->assertSame( 'article', $rows['dev.to']['format'] );
		$this->assertSame( '', $rows['threads.net']['connector'] );
	}

	public function test_calendar_dates_month_and_dedup(): void {
		$row  = static function ( array $entry, string $platform = 'x.com' ): array {
			return array(
				'post_id'  => 7,
				'platform' => $platform,
				'entry'    => $entry,
			);
		};
		$rows = array(
			$row( array( 'status' => 'planned', 'run_at' => '2026-11-10T10:00:00+00:00', 'updated_at' => '2026-11-01T10:00:00+00:00' ) ),
			$row( array( 'status' => 'published', 'run_at' => '2026-11-20T10:00:00+00:00', 'updated_at' => '2026-11-11T10:00:00+00:00' ), 'dev.to' ),
			$row( array( 'status' => 'failed', 'updated_at' => '2026-11-11T11:00:00+00:00' ), 'dev.to' ),
			$row( array( 'status' => 'planned', 'run_at' => '' ), 'medium.com' ),
			$row( array( 'status' => 'published', 'updated_at' => '2026-12-01T10:00:00+00:00' ), 'linkedin.com' ),
		);

		$events = Calendar::events_from_rows( $rows, '2026-11', new DateTimeZone( 'UTC' ) );
		$this->assertCount( 2, $events );
		$this->assertSame( array( '2026-11-10', 'x.com' ), array( $events[0]['date'], $events[0]['platform'] ) );
		$this->assertSame( array( '2026-11-11', 'dev.to', 'published' ), array( $events[1]['date'], $events[1]['platform'], $events[1]['status'] ) );
	}

	public function test_calendar_uses_site_time_zone(): void {
		$rows   = array(
			array(
				'post_id'  => 1,
				'platform' => 'x.com',
				'entry'    => array(
					'status'     => 'published',
					'updated_at' => '2026-10-31T23:30:00+00:00',
				),
			),
		);
		$events = Calendar::events_from_rows( $rows, '2026-11', new DateTimeZone( 'Europe/Kyiv' ) );
		$this->assertSame( '2026-11-01', $events[0]['date'] );
		$this->assertSame( '01:30', $events[0]['time'] );
	}

	public function test_calendar_stats_group_statuses(): void {
		$events = array_map(
			static fn( string $status ): array => array( 'status' => $status ),
			array( 'published', 'published', 'planned', 'queued', 'processing', 'manual_pending', 'failed', 'unknown' )
		);
		$this->assertSame(
			array(
				'published' => 2,
				'scheduled' => 3,
				'waiting'   => 1,
				'failed'    => 1,
			),
			Calendar::stats( $events )
		);
	}

	public function test_icons_svg_in_brand_color_and_letters_without_svg(): void {
		$icon = Platform_Icons::render( 'youtube.com' );
		$this->assertStringContainsString( '<svg', $icon );
		$this->assertStringContainsString( 'fill="#ff0000"', $icon );
		$this->assertStringContainsString( 'viewBox="0 0 512 512"', Platform_Icons::render( 'dev.to' ) );

		$this->assertStringContainsString( '#0085ff', Platform_Icons::render( 'bsky.app' ) );
		$this->assertStringContainsString( '>TH<', Platform_Icons::render( 'threads.net' ) );
	}

	public function test_custom_icon_wins_and_can_be_removed(): void {
		$this->assertIsArray( Platform_Registry::update( 'youtube.com', array( 'icon' => 'https://a.test/yt.png' ) ) );
		$icon = Platform_Icons::render( 'youtube.com' );
		$this->assertStringContainsString( '<img', $icon );
		$this->assertStringContainsString( 'https://a.test/yt.png', $icon );
		$this->assertSame( 'youtube.com', Platform_Registry::get( 'youtube.com' )['connector'] );

		Platform_Registry::update( 'youtube.com', array( 'icon' => '' ) );
		$this->assertStringContainsString( '<svg', Platform_Icons::render( 'youtube.com' ) );
	}

	public function test_scheduled_lists_active_dated_work_soonest_first(): void {
		$row  = static function ( int $post, string $platform, string $status, string $run_at ): array {
			return array(
				'post_id'  => $post,
				'platform' => $platform,
				'entry'    => array(
					'status' => $status,
					'mode'   => 'api',
					'run_at' => $run_at,
				),
			);
		};
		$rows = array(
			$row( 1, 'x.com', 'planned', '2026-12-02T10:00:00+00:00' ),
			$row( 2, 'dev.to', 'queued', '2026-12-01T10:00:00+00:00' ),
			$row( 2, 'dev.to', 'queued', '2026-11-01T10:00:00+00:00' ),
			$row( 3, 'x.com', 'planned', '' ),
			$row( 4, 'x.com', 'published', '2026-12-03T10:00:00+00:00' ),
			$row( 5, 'x.com', 'manual_pending', '2026-12-03T10:00:00+00:00' ),
			$row( 6, 'x.com', 'processing', '2026-12-04T10:00:00+00:00' ),
		);

		$all = Calendar::scheduled_from_rows( $rows, '', 50 );
		$this->assertSame( array( 2, 1, 6 ), array_column( $all, 'post_id' ) );
		$this->assertSame( '2026-12-01T10:00:00+00:00', $all[0]['run_at'] );

		$this->assertSame( array( 1, 6 ), array_column( Calendar::scheduled_from_rows( $rows, 'x.com', 50 ), 'post_id' ) );
		$this->assertCount( 1, Calendar::scheduled_from_rows( $rows, '', 1 ) );
	}

	public function test_platform_counts_published_and_scheduled(): void {
		$counts = Calendar::platform_counts(
			array(
				array( 'platform' => 'x.com', 'status' => 'published' ),
				array( 'platform' => 'x.com', 'status' => 'published' ),
				array( 'platform' => 'x.com', 'status' => 'queued' ),
				array( 'platform' => 'x.com', 'status' => 'planned' ),
				array( 'platform' => 'x.com', 'status' => 'processing' ),
				array( 'platform' => 'x.com', 'status' => 'failed' ),
				array( 'platform' => 'x.com', 'status' => 'manual_pending' ),
				array( 'platform' => 'off.com', 'status' => 'published' ),
				array( 'platform' => 'gone.com', 'status' => 'queued' ),
				array( 'platform' => 'never.com', 'status' => 'failed' ),
			),
			array(
				'dev.to'  => array( 'enabled' => true ),
				'x.com'   => array( 'enabled' => true ),
				'off.com' => array( 'enabled' => false ),
				'hid.com' => array( 'enabled' => false ),
			)
		);

		$this->assertSame(
			array(
				'dev.to'   => array( 'published' => 0, 'scheduled' => 0 ),
				'x.com'    => array( 'published' => 2, 'scheduled' => 3 ),
				'off.com'  => array( 'published' => 1, 'scheduled' => 0 ),
				'gone.com' => array( 'published' => 0, 'scheduled' => 1 ),
			),
			$counts
		);
	}

	public function test_site_rows_publish_and_update(): void {
		$post = static function ( int $id, string $date, string $modified ): array {
			return array(
				'post_id'       => $id,
				'post_type'     => 'page',
				'post_date'     => $date,
				'post_modified' => $modified,
			);
		};

		$rows = Calendar::site_rows(
			array(
				$post( 1, '2026-10-05 09:30:00', '2026-10-05 18:00:00' ), // Same day: only the publication.
				$post( 2, '2026-10-05 10:00:00', '2026-10-20 11:15:00' ), // Both in the month.
				$post( 3, '2026-09-01 10:00:00', '2026-10-21 12:00:00' ), // Only the update.
				$post( 4, '2026-10-22 10:00:00', '2026-11-02 12:00:00' ), // Only the publication.
				$post( 5, '2026-09-01 10:00:00', '2026-09-02 12:00:00' ), // Other month.
				$post( 6, '2026-10-23 10:00:00', '2026-10-01 12:00:00' ), // Modified before the date: no update.
			),
			'2026-10'
		);

		$this->assertSame(
			array( '1:publish', '2:publish', '2:update', '3:update', '4:publish', '6:publish' ),
			array_map( static fn( array $r ): string => $r['post_id'] . ':' . $r['action'], $rows )
		);
		$this->assertSame( array( '2026-10-20', '11:15' ), array( $rows[2]['date'], $rows[2]['time'] ) );
	}

	public function test_migration_takes_the_last_row_per_platform(): void {
		$latest = Seo_Migration::latest_from_log(
			array(
				array( 'platform' => 'DEV.to', 'url' => 'https://dev.to/a/old', 'slug' => 'old', 'posted_at' => '2026-01-01' ),
				array( 'platform' => 'dev.to', 'url' => 'https://dev.to/a/new', 'slug' => 'new', 'posted_at' => '2026-02-01' ),
				'junk',
				array( 'url' => 'no platform' ),
			)
		);
		$this->assertSame( array( 'dev.to' ), array_keys( $latest ) );
		$this->assertSame( 'new', $latest['dev.to']['slug'] );
		$this->assertSame( array(), Seo_Migration::latest_from_log( 'broken' ) );
	}

	public function test_migration_marks_published_and_blocks_a_second_post(): void {
		$latest = array(
			'dev.to' => array(
				'url'       => 'https://dev.to/a/new',
				'slug'      => 'new',
				'posted_at' => '2026-02-01T00:00:00+00:00',
			),
		);
		$result = Seo_Migration::merge_log( array(), array(), $latest );

		$this->assertSame( array( 'dev.to' ), $result['added'] );
		$this->assertSame( 'published', $result['index']['dev.to']['status'] );
		$this->assertSame( 'migration', $result['index']['dev.to']['via'] );
		$this->assertSame( '2026-02-01T00:00:00+00:00', $result['index']['dev.to']['updated_at'] );
		$this->assertSame( 'new', $result['map']['dev.to']['id'] );
	}

	public function test_migration_runs_twice_and_keeps_social_results(): void {
		$latest = array(
			'dev.to' => array(
				'url'       => 'https://dev.to/a/seo',
				'slug'      => 'seo',
				'posted_at' => '2026-02-01T00:00:00+00:00',
			),
		);
		$first  = Seo_Migration::merge_log( array(), array(), $latest );
		$second = Seo_Migration::merge_log( $first['index'], $first['map'], $latest );
		$this->assertSame( $first['index'], $second['index'] );
		$this->assertSame( array( 'dev.to' ), $second['kept'] );

		$social = Seo_Migration::merge_log(
			array(
				'dev.to' => array(
					'status' => 'published',
					'url'    => 'https://dev.to/a/api',
				),
			),
			array( 'dev.to' => array( 'id' => '42' ) ),
			$latest
		);
		$this->assertSame( 'https://dev.to/a/api', $social['index']['dev.to']['url'] );
		$this->assertSame( '42', $social['map']['dev.to']['id'] );
	}

	public function test_rest_helpers_match_seo_helper(): void {
		$this->assertSame( 'my-post-1a', Rest_Crosspost::slug_from_url( 'https://dev.to/user/my-post-1a/?x=1' ) );
		$this->assertSame( '', Rest_Crosspost::slug_from_url( 'https://dev.to/' ) );
		$this->assertSame( 'none', Rest_Crosspost::variant_state( 'open', '', null ) );
		$this->assertSame( 'missing', Rest_Crosspost::variant_state( 'ready', 'a.md', array() ) );
		$this->assertSame( 'empty', Rest_Crosspost::variant_state( 'ready', 'a.md', array( 'a.md' => 0 ) ) );
		$this->assertSame( 'skipped', Rest_Crosspost::variant_state( 'skipped', 'a.md', array( 'a.md' => 1 ) ) );
		$this->assertSame( 'ready', Rest_Crosspost::variant_state( 'posted', 'a.md', null ) );
		$this->assertSame( 'draft', Rest_Crosspost::variant_state( 'open', 'a.md', null ) );
	}
}
