<?php
/**
 * Pinterest adapter: pin type and description.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Core\Media_Package;
use ForWP\Social\Modules\Posting\Adapters\Pinterest_Adapter;
use PHPUnit\Framework\TestCase;

final class PinterestTest extends TestCase {

	public function test_pin_type_follows_the_package(): void {
		$video = (string) tempnam( sys_get_temp_dir(), 'pin' );
		$this->assertSame( 'image', Pinterest_Adapter::pin_type( new Media_Package( array() ) ) );
		$this->assertSame( 'video', Pinterest_Adapter::pin_type( new Media_Package( array( 'video_path' => $video ) ) ) );
		$this->assertSame( 'image', Pinterest_Adapter::pin_type( new Media_Package( array( 'video_path' => $video, 'entry' => array( 'pin_type' => 'image' ) ) ) ) );
		unlink( $video );
	}

	public function test_description_adds_missing_hashtags_and_respects_the_limit(): void {
		$package = new Media_Package(
			array(
				'description' => 'OOP in WordPress #WordPress',
				'tags'        => array( 'WordPress', 'PHP' ),
			)
		);
		$this->assertSame( "OOP in WordPress #WordPress\n\n#PHP", Pinterest_Adapter::description( $package ) );

		$long = new Media_Package( array( 'description' => str_repeat( 'a', 900 ) ) );
		$this->assertSame( 800, strlen( Pinterest_Adapter::description( $long ) ) );
	}
}
