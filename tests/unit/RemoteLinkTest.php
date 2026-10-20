<?php
/**
 * Linking a post to a video already on the platform.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Posting\Remote_Link;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Remote_Link
 */
class RemoteLinkTest extends TestCase {

	public function test_youtube_id_from_id_and_links(): void {
		$this->assertSame( 'ILgsDV504SU', Remote_Link::youtube_id( 'ILgsDV504SU' ) );
		$this->assertSame( 'ILgsDV504SU', Remote_Link::youtube_id( 'https://www.youtube.com/shorts/ILgsDV504SU' ) );
		$this->assertSame( 'ILgsDV504SU', Remote_Link::youtube_id( 'https://www.youtube.com/watch?v=ILgsDV504SU&list=PLx' ) );
		$this->assertSame( 'ILgsDV504SU', Remote_Link::youtube_id( 'https://youtu.be/ILgsDV504SU?t=3' ) );
		$this->assertSame( '', Remote_Link::youtube_id( 'not a video' ) );
	}

	public function test_rank_puts_the_closest_title_first(): void {
		$videos = array(
			array( 'id' => 'a', 'title' => 'Everything in functions.php? Split It with MVC #WordPress' ),
			array( 'id' => 'b', 'title' => 'AI Writes the Code. You Own the Architecture. #WordPress #OOP' ),
			array( 'id' => 'c', 'title' => 'Fiat' ),
		);
		$found  = Remote_Link::rank( 'AI Writes the Code. You Own the Architecture.', $videos );

		$this->assertSame( 'b', $found[0]['id'] );
		$this->assertCount( 1, $found );
	}
}
