<?php
/**
 * LinkedIn export: xlsx reader and sheet parser (synthetic workbook, no personal data).
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Modules\Analytics\Sources\LinkedIn_Source;
use ForWP\Social\Modules\Analytics\Xlsx_Reader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Modules\Analytics\Xlsx_Reader
 * @covers \ForWP\Social\Modules\Analytics\Sources\LinkedIn_Source
 */
class LinkedInImportTest extends TestCase {

	/**
	 * Workbook with the sheets of a LinkedIn Aggregate analytics export.
	 */
	private function workbook(): string {
		$sheets = array(
			'DISCOVERY'             => array( array( 'Overall Performance', '10/1/2026 - 10/2/2026' ), array( 'Impressions', '300' ), array( 'Members reached', '120' ) ),
			'ENGAGEMENT'            => array( array( 'Date', 'Impressions', 'Engagements' ), array( '10/1/2026', '100', '3' ), array( '10/2/2026', '200', '5' ) ),
			'MOST RECENT POSTS'     => array(
				array( 'Post URL', 'Post Publish Date', 'Post type', 'Impressions', 'Members reached', 'Views', 'Reactions', 'Comments', 'Reposts', 'Saves', 'Sends' ),
				array( 'https://www.linkedin.com/posts/user_hello-world-ugcPost-123-ab', '10/2/2026', 'Video', '80', '40', '30', '2', '1', '0', '0', '0' ),
			),
			'TOP POSTS'             => array(
				array( 'Maximum of 50 posts' ),
				array(),
				array( 'Post URL', 'Post Publish Date', 'Engagements', '', 'Post URL', 'Post Publish Date', 'Impressions' ),
				array( 'https://www.linkedin.com/posts/user_hello-world-ugcPost-123-ab', '10/2/2026', '4', '', 'https://www.linkedin.com/feed/update/urn:li:share:9', '9/30/2026', '1,500' ),
			),
			'FOLLOWERS'             => array( array( 'Total followers on 10/2/2026', '1000' ), array(), array( 'Date', 'New followers' ), array( '10/1/2026', '7' ), array( '10/2/2026', '9' ) ),
			'AUDIENCE DEMOGRAPHICS' => array( array( 'Top Demographics', 'Value', 'Percentage' ), array( 'Industry', 'Software', '< 1%' ) ),
		);

		$book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		$rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$file = tempnam( sys_get_temp_dir(), 'li' ) . '.xlsx';
		$zip  = new \ZipArchive();
		$zip->open( $file, \ZipArchive::CREATE );
		$n = 0;
		foreach ( $sheets as $name => $rows ) {
			++$n;
			$book .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Target="worksheets/sheet' . $n . '.xml" Type="x"/>';
			$xml   = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
			foreach ( $rows as $r => $cells ) {
				$xml .= '<row r="' . ( $r + 1 ) . '">';
				foreach ( $cells as $c => $text ) {
					$xml .= '<c r="' . chr( 65 + $c ) . ( $r + 1 ) . '" t="inlineStr"><is><t>' . htmlspecialchars( $text ) . '</t></is></c>';
				}
				$xml .= '</row>';
			}
			$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $xml . '</sheetData></worksheet>' );
		}
		$zip->addFromString( 'xl/workbook.xml', $book . '</sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels . '</Relationships>' );
		$zip->close();

		return $file;
	}

	public function test_reader_and_parser(): void {
		$file   = $this->workbook();
		$sheets = Xlsx_Reader::sheets( $file );
		unlink( $file );

		$this->assertIsArray( $sheets );
		$this->assertSame( '< 1%', $sheets['AUDIENCE DEMOGRAPHICS'][1][2] );

		$data = LinkedIn_Source::parse( $sheets );

		$this->assertSame( '10/1/2026 - 10/2/2026', $data['period'] );
		$this->assertSame(
			array(
				'2026-10-01' => array( 'impressions' => 100.0, 'engagements' => 3.0, 'followers' => 7.0 ),
				'2026-10-02' => array( 'impressions' => 200.0, 'engagements' => 5.0, 'followers' => 9.0 ),
			),
			$data['daily']
		);
		$this->assertSame( 1000.0, $data['account']['followers'] );
		$this->assertSame( 120.0, $data['account']['reached'] );

		$post = $data['posts']['https://www.linkedin.com/posts/user_hello-world-ugcPost-123-ab'];
		$this->assertSame( 'hello world', $post['title'] );
		$this->assertSame( 4.0, $post['engagements'] );
		$this->assertSame( 80.0, $post['impressions'] );
		$this->assertSame( 'Video', $post['type'] );
		$this->assertSame( 1500.0, $data['posts']['https://www.linkedin.com/feed/update/urn:li:share:9']['impressions'] );
		$this->assertSame( array( array( 'Software', '< 1%' ) ), $data['demographics']['Industry'] );
	}

	public function test_column_and_day(): void {
		$this->assertSame( 0, Xlsx_Reader::column( 'A1' ) );
		$this->assertSame( 27, Xlsx_Reader::column( 'AB12' ) );
		$this->assertSame( '2026-08-25', LinkedIn_Source::day( '8/25/2026' ) );
		$this->assertNull( LinkedIn_Source::day( '25.08.2026' ) );
	}
}
