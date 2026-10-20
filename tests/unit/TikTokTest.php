<?php
/**
 * TikTok client / adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Api\TikTok_Client;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Modules\Posting\Adapters\TikTok_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Api\TikTok_Client
 * @covers \ForWP\Social\Modules\Posting\Adapters\TikTok_Adapter
 */
class TikTokTest extends TestCase {

	private const MB = 1048576;

	public function test_small_file_is_one_chunk(): void {
		$this->assertSame( array( 'chunk_size' => 8535626, 'total_chunk_count' => 1 ), TikTok_Client::chunk_plan( 8535626 ) );
	}

	public function test_large_file_puts_remainder_in_last_chunk(): void {
		$size = 25 * self::MB + 123;
		$plan = TikTok_Client::chunk_plan( $size );

		$this->assertSame( 10 * self::MB, $plan['chunk_size'] );
		$this->assertSame( 2, $plan['total_chunk_count'] );
		$last = $size - ( $plan['total_chunk_count'] - 1 ) * $plan['chunk_size'];
		$this->assertGreaterThanOrEqual( 5 * self::MB, $plan['chunk_size'] );
		$this->assertLessThanOrEqual( 128 * self::MB, $last );
	}

	public function test_privacy_falls_back_to_self_only_for_unaudited_apps(): void {
		$this->assertSame( 'PUBLIC_TO_EVERYONE', TikTok_Adapter::resolve_privacy( 'public', array( 'PUBLIC_TO_EVERYONE', 'SELF_ONLY' ) ) );
		$this->assertSame( 'SELF_ONLY', TikTok_Adapter::resolve_privacy( 'public', array( 'SELF_ONLY' ) ) );
		$this->assertSame( 'SELF_ONLY', TikTok_Adapter::resolve_privacy( 'private', array( 'PUBLIC_TO_EVERYONE', 'SELF_ONLY' ) ) );
		$this->assertSame( '', TikTok_Adapter::resolve_privacy( 'public', array() ) );
	}

	public function test_caption_adds_missing_hashtags_and_respects_limit(): void {
		$package = new Media_Package(
			array(
				'title'       => 'Title',
				'description' => 'Demo #WordPress',
				'tags'        => array( 'WordPress', 'FAQ schema', 'c++' ),
			)
		);
		$this->assertSame( "Demo #WordPress\n\n#FAQschema #c", TikTok_Adapter::caption( $package ) );

		$long = new Media_Package( array( 'description' => str_repeat( 'я', 3000 ) ) );
		$this->assertSame( 2200, mb_strlen( TikTok_Adapter::caption( $long ) ) );
	}
}
