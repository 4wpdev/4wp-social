<?php
/**
 * DEV.to article file parsing and adapter pure logic.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Core\Media_Package;
use ForWP\Social\Modules\Posting\Adapters\DevTo_Adapter;
use ForWP\Social\Modules\Posting\Article_Parser;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \ForWP\Social\Modules\Posting\Article_Parser
 * @covers \ForWP\Social\Modules\Posting\Adapters\DevTo_Adapter
 */
class DevToTest extends TestCase {

	public function test_front_matter_placeholders_and_title(): void {
		$text   = "---\ntitle: \"From matter\"\ncanonical_url: {{original.url}}\nseries: {{unknown}}\ntags: wp, php\n---\n# Heading\n\nBody {{original.url}}\n";
		$parsed = Article_Parser::parse( $text, array( '{{original.url}}' => 'https://4wp.dev/a/' ) );

		$this->assertSame( 'From matter', $parsed['title'] );
		$this->assertSame( 'Body https://4wp.dev/a/', $parsed['body'] );
		$this->assertSame( 'https://4wp.dev/a/', $parsed['fields']['canonical_url'] );
		$this->assertSame( 'wp, php', $parsed['fields']['tags'] );
		$this->assertArrayNotHasKey( 'series', $parsed['fields'] );
	}

	public function test_leading_heading_becomes_title_and_code_keeps_braces(): void {
		$parsed = Article_Parser::parse( "# Title\n\n```\n{{ value }}\n```" );

		$this->assertSame( 'Title', $parsed['title'] );
		$this->assertSame( "```\n{{ value }}\n```", $parsed['body'] );
	}

	public function test_tags_are_cleaned_and_limited_to_four(): void {
		$tags = DevTo_Adapter::tags( array( 'WordPress', 'FAQ schema', 'wordpress', 'Плагін', 'php', 'a-b', 'extra' ) );

		$this->assertSame( array( 'wordpress', 'faqschema', 'php', 'ab' ), $tags );
	}

	public function test_public_package_is_published_with_canonical(): void {
		$payload = DevTo_Adapter::payload(
			new Media_Package(
				array(
					'title'   => 'T',
					'privacy' => 'public',
					'tags'    => array( 'WP' ),
					'article' => array(
						'body'          => 'B',
						'canonical_url' => 'https://4wp.dev/a/',
					),
				)
			)
		);

		$this->assertTrue( $payload['published'] );
		$this->assertSame( 'B', $payload['body_markdown'] );
		$this->assertSame( 'https://4wp.dev/a/', $payload['canonical_url'] );
		$this->assertSame( 'wp', $payload['tags'] );
		$this->assertArrayNotHasKey( 'series', $payload );
	}

	public function test_non_public_or_file_draft_stays_a_draft(): void {
		$private = new Media_Package( array( 'privacy' => 'private' ) );
		$draft   = new Media_Package(
			array(
				'privacy' => 'public',
				'article' => array( 'draft' => true ),
			)
		);

		$this->assertFalse( DevTo_Adapter::payload( $private )['published'] );
		$this->assertFalse( DevTo_Adapter::payload( $draft )['published'] );
	}

	public function test_only_rate_limit_and_server_errors_retry(): void {
		$this->assertTrue( DevTo_Adapter::is_retryable( new WP_Error( 'x', 'x', array( 'status' => 429 ) ) ) );
		$this->assertTrue( DevTo_Adapter::is_retryable( new WP_Error( 'x', 'x', array( 'status' => 503 ) ) ) );
		$this->assertFalse( DevTo_Adapter::is_retryable( new WP_Error( 'x', 'x', array( 'status' => 422 ) ) ) );
		$this->assertFalse( DevTo_Adapter::is_retryable( new WP_Error( 'http_request_failed', 'timeout' ) ) );
	}
}
