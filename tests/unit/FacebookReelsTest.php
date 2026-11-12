<?php
/**
 * Facebook Reels adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Posting\Adapters\Facebook_Reels_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Adapters\Facebook_Reels_Adapter
 */
class FacebookReelsTest extends TestCase {

	public function test_only_public_packages_are_published(): void {
		$this->assertSame( 'PUBLISHED', Facebook_Reels_Adapter::video_state( 'public' ) );
		$this->assertSame( 'DRAFT', Facebook_Reels_Adapter::video_state( 'private' ) );
		$this->assertSame( 'DRAFT', Facebook_Reels_Adapter::video_state( 'unlisted' ) );
	}

	public function test_permalink_is_absolute(): void {
		$this->assertSame( 'https://www.facebook.com/reel/42', Facebook_Reels_Adapter::permalink( '42', '/reel/42' ) );
		$this->assertSame( 'https://www.facebook.com/reel/42', Facebook_Reels_Adapter::permalink( '42', '' ) );
		$this->assertSame( 'https://fb.watch/x', Facebook_Reels_Adapter::permalink( '42', 'https://fb.watch/x' ) );
	}

	public function test_status_processing_then_published(): void {
		$processing = Facebook_Reels_Adapter::result_from_status(
			'42',
			array(
				'status' => array(
					'video_status'     => 'processing',
					'publishing_phase' => array( 'status' => 'not_started' ),
				),
			)
		);
		$this->assertTrue( $processing->is_processing() );

		$done = Facebook_Reels_Adapter::result_from_status(
			'42',
			array(
				'status'        => array(
					'video_status'     => 'ready',
					'publishing_phase' => array(
						'status'         => 'complete',
						'publish_status' => 'published',
					),
				),
				'permalink_url' => '/reel/42',
			)
		);
		$this->assertTrue( $done->is_published() );
		$this->assertSame( 'https://www.facebook.com/reel/42', $done->remote_url() );
	}

	public function test_status_error_fails_with_message(): void {
		$res = Facebook_Reels_Adapter::result_from_status(
			'42',
			array(
				'status' => array(
					'video_status'     => 'error',
					'processing_phase' => array(
						'status' => 'error',
						'errors' => array( array( 'message' => 'Video too short' ) ),
					),
				),
			)
		);
		$this->assertTrue( $res->is_failed() );
		$this->assertSame( 'Video too short', $res->message() );
	}
}
