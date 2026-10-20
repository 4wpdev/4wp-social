<?php
/**
 * Platform copy parser tests (real 4wp-faq YouTube file).
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Posting\Platform_Copy_Parser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Posting\Platform_Copy_Parser
 */
class Platform_Copy_ParserTest extends TestCase {

	public function test_parses_real_youtube_copy_file(): void {
		$copy = Platform_Copy_Parser::parse( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/4wp-faq-overview-youtube.md' ) );

		$this->assertSame( '4WP FAQ: FAQ Schema for the WordPress Core Accordion (60s Demo)', $copy['title'] );
		$this->assertStringStartsWith( 'Not another FAQ block.', $copy['description'] );
		$this->assertStringContainsString( '#WordPress #FAQSchema #Gutenberg', $copy['description'] );
		$this->assertContains( 'WordPress FAQ plugin', $copy['tags'] );
		$this->assertContains( '4wp.dev', $copy['tags'] );
		$this->assertSame( '', $copy['caption'] );
	}

	public function test_first_block_per_section_and_caption(): void {
		$md   = "## Caption\n```\nShort caption #wp\n```\n```\nsecond block ignored\n```\n## Pinned comment\n```\nnot a section\n```";
		$copy = Platform_Copy_Parser::parse( $md );

		$this->assertSame( 'Short caption #wp', $copy['caption'] );
		$this->assertSame( '', $copy['title'] );
	}

	public function test_empty_input(): void {
		$copy = Platform_Copy_Parser::parse( '' );
		$this->assertSame( array(), $copy['tags'] );
		$this->assertSame( '', $copy['description'] );
	}
}
