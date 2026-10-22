<?php
/**
 * Bluesky client / adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Api\Bluesky_Client;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Modules\Posting\Adapters\Bluesky_Adapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Api\Bluesky_Client
 * @covers \ForWP\Social\Modules\Posting\Adapters\Bluesky_Adapter
 */
class BlueskyTest extends TestCase {

	public function test_facets_use_utf8_byte_offsets(): void {
		$text   = "Привіт https://4wp.dev/faq/. #WordPress";
		$facets = Bluesky_Client::facets( $text );

		$this->assertCount( 2, $facets );
		$link = $facets[0];
		$this->assertSame( 'https://4wp.dev/faq/', $link['features'][0]['uri'] );
		$this->assertSame( 'https://4wp.dev/faq/', substr( $text, $link['index']['byteStart'], $link['index']['byteEnd'] - $link['index']['byteStart'] ) );

		$tag = $facets[1];
		$this->assertSame( 'WordPress', $tag['features'][0]['tag'] );
		$this->assertSame( '#WordPress', substr( $text, $tag['index']['byteStart'], $tag['index']['byteEnd'] - $tag['index']['byteStart'] ) );
	}

	public function test_numbers_and_url_fragments_are_not_tags(): void {
		$this->assertSame( array(), Bluesky_Client::facets( 'Issue #123 and a#b' ) );
	}

	public function test_text_adds_hashtags_only_while_they_fit(): void {
		$package = new Media_Package(
			array(
				'description' => 'Demo #WordPress',
				'tags'        => array( 'WordPress', 'FAQ schema' ),
			)
		);
		$this->assertSame( "Demo #WordPress\n\n#FAQschema", Bluesky_Adapter::text( $package ) );

		$full = new Media_Package( array( 'description' => str_repeat( 'a', 295 ), 'tags' => array( 'LongTag' ) ) );
		$this->assertSame( str_repeat( 'a', 295 ), Bluesky_Adapter::text( $full ) );
	}

	public function test_long_text_is_cut_to_300(): void {
		$text = Bluesky_Adapter::text( new Media_Package( array( 'description' => str_repeat( 'я', 400 ) ) ) );
		$this->assertSame( 300, mb_strlen( $text ) );
		$this->assertStringEndsWith( '…', $text );
	}

	public function test_rkey_from_uri(): void {
		$this->assertSame( '3lxyz', Bluesky_Client::rkey( 'at://did:plc:abc/app.bsky.feed.post/3lxyz' ) );
	}
}
