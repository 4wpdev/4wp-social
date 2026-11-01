<?php
/**
 * Platforms missing from meta.json reuse the package video.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Posting\Package_Reader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Package_Reader
 */
class PackageReaderTest extends TestCase {

	public function test_borrowed_item_takes_media_but_not_the_copy(): void {
		$snapshot = array(
			'youtube.com' => array(
				'entry'         => array( 'privacy' => 'private' ),
				'copy'          => array( 'title' => 'YouTube title #Shorts' ),
				'video_id'      => 11,
				'captions_id'   => 12,
				'captions_name' => 'clip.en.srt',
				'cover_id'      => 13,
			),
		);

		$this->assertSame(
			array(
				'video_id'      => 11,
				'captions_id'   => 12,
				'captions_name' => 'clip.en.srt',
				'cover_id'      => 13,
				'entry'         => array(),
				'copy'          => array(),
			),
			Package_Reader::borrowed_item( $snapshot )
		);
	}

	public function test_no_video_means_nothing_to_borrow(): void {
		$this->assertNull( Package_Reader::borrowed_item( array() ) );
		$this->assertNull( Package_Reader::borrowed_item( array( 'tiktok.com' => array( 'entry' => array() ) ) ) );
	}
}
