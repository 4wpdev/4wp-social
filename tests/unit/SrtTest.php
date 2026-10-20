<?php
/**
 * SRT → transcript.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Video\Srt;
use PHPUnit\Framework\TestCase;

final class SrtTest extends TestCase {

	public function test_srt_becomes_plain_text(): void {
		$srt = "\xEF\xBB\xBF1\r\n00:00:00,000 --> 00:00:02,000\r\n<i>Cut the head</i> off WordPress.\r\n\r\n2\r\n00:00:02,000 --> 00:00:04,000\r\nIt serves the data.\r\nIt serves the data.\r\n";

		$this->assertSame( 'Cut the head off WordPress. It serves the data.', Srt::to_text( $srt ) );
	}

	public function test_vtt_header_is_skipped(): void {
		$this->assertSame( 'Hello', Srt::to_text( "WEBVTT\n\n00:00.000 --> 00:01.000\nHello\n" ) );
	}
}
