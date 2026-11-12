<?php
/**
 * Instagram Reels adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Posting\Adapters\Instagram_Reels_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Adapters\Instagram_Reels_Adapter
 */
class InstagramReelsTest extends TestCase {

	public function test_container_status_maps_to_next_step(): void {
		$this->assertSame( 'publish', Instagram_Reels_Adapter::next_step( 'FINISHED' ) );
		$this->assertSame( 'wait', Instagram_Reels_Adapter::next_step( 'IN_PROGRESS' ) );
		$this->assertSame( 'wait', Instagram_Reels_Adapter::next_step( '' ) );
		$this->assertSame( 'failed', Instagram_Reels_Adapter::next_step( 'ERROR' ) );
		$this->assertSame( 'failed', Instagram_Reels_Adapter::next_step( 'EXPIRED' ) );
	}
}
