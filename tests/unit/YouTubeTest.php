<?php
/**
 * YouTube adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Core\Media_Package;
use ForWP\Social\Modules\Posting\Adapters\YouTube_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Adapters\YouTube_Adapter
 */
class YouTubeTest extends TestCase {

	public function test_resource_cleans_title_and_maps_privacy(): void {
		$package  = new Media_Package(
			array(
				'title'       => '<b>' . str_repeat( 'x', 120 ),
				'description' => 'Line <one>',
				'tags'        => array( 'WordPress', 'FAQ schema' ),
				'privacy'     => 'weird',
				'entry'       => array( 'category_id' => '22' ),
			)
		);
		$resource = YouTube_Adapter::build_resource( $package );

		$this->assertSame( 100, mb_strlen( $resource['snippet']['title'] ) );
		$this->assertStringNotContainsString( '<', $resource['snippet']['title'] );
		$this->assertSame( 'Line one', $resource['snippet']['description'] );
		$this->assertSame( '22', $resource['snippet']['categoryId'] );
		$this->assertSame( 'public', $resource['status']['privacyStatus'] );
		$this->assertFalse( $resource['status']['selfDeclaredMadeForKids'] );
	}

	public function test_resource_sets_language_from_entry_or_captions(): void {
		$from_entry = YouTube_Adapter::build_resource( new Media_Package( array( 'entry' => array( 'language' => 'uk' ) ) ) );
		$this->assertSame( 'uk', $from_entry['snippet']['defaultLanguage'] );
		$this->assertSame( 'uk', $from_entry['snippet']['defaultAudioLanguage'] );

		$from_captions = YouTube_Adapter::build_resource( new Media_Package( array( 'captions_language' => 'en' ) ) );
		$this->assertSame( 'en', $from_captions['snippet']['defaultLanguage'] );
	}

	public function test_tags_stay_under_500_chars(): void {
		$tags   = array_fill( 0, 100, 'wordpress plugin' );
		$result = YouTube_Adapter::limit_tags( $tags );
		$total  = 0;
		foreach ( $result as $i => $tag ) {
			$total += strlen( $tag ) + 2 + ( $i ? 1 : 0 );
		}
		$this->assertLessThanOrEqual( 500, $total );
		$this->assertNotEmpty( $result );
	}
}
