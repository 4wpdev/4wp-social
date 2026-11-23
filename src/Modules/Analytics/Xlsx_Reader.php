<?php
/**
 * Minimal .xlsx reader: sheet name => rows of cell text (ZipArchive + SimpleXML, no library).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads values only (shared strings, inline strings, numbers); no styles or formulas.
 */
final class Xlsx_Reader {

	/**
	 * All sheets of a workbook.
	 *
	 * @param string $path File path.
	 * @return array<string, array<int, array<int, string>>>|WP_Error Sheet name => rows (0-based) => columns (0-based) => text.
	 */
	public static function sheets( string $path ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new WP_Error( 'forwp_social_xlsx', __( 'The PHP zip extension is needed to read .xlsx files.', '4wp-social' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'forwp_social_xlsx', __( 'Not a valid .xlsx file.', '4wp-social' ) );
		}

		$book = self::xml( $zip, 'xl/workbook.xml' );
		$rels = self::xml( $zip, 'xl/_rels/workbook.xml.rels' );
		if ( ! $book || ! $rels ) {
			$zip->close();
			return new WP_Error( 'forwp_social_xlsx', __( 'Not a valid .xlsx file.', '4wp-social' ) );
		}

		$targets = array();
		foreach ( $rels->Relationship as $rel ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML element.
			$targets[ (string) $rel['Id'] ] = 'xl/' . ltrim( preg_replace( '#^/?xl/#', '', (string) $rel['Target'] ), '/' );
		}

		$strings = array();
		$shared  = self::xml( $zip, 'xl/sharedStrings.xml' );
		if ( $shared ) {
			foreach ( $shared->si as $si ) {
				$strings[] = self::text( $si );
			}
		}

		$out = array();
		foreach ( $book->sheets->sheet as $sheet ) {
			$rid   = (string) $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
			$xml   = isset( $targets[ $rid ] ) ? self::xml( $zip, $targets[ $rid ] ) : null;
			$rows  = array();
			$index = 0;
			if ( $xml ) {
				foreach ( $xml->sheetData->row as $row ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML element.
					$index = isset( $row['r'] ) ? (int) $row['r'] - 1 : $index;
					$cells = array();
					foreach ( $row->c as $c ) {
						$col           = self::column( (string) $c['r'] );
						$type          = (string) $c['t'];
						$value         = isset( $c->v ) ? (string) $c->v : '';
						$cells[ $col ] = 's' === $type ? ( $strings[ (int) $value ] ?? '' ) : ( 'inlineStr' === $type ? self::text( $c->is ) : $value );
					}
					$rows[ $index ] = $cells ? self::fill( $cells ) : array();
					++$index;
				}
			}
			$out[ (string) $sheet['name'] ] = $rows;
		}
		$zip->close();

		return $out;
	}

	/**
	 * Parsed XML part, or null.
	 *
	 * @param \ZipArchive $zip  Archive.
	 * @param string      $name Part name.
	 */
	private static function xml( \ZipArchive $zip, string $name ): ?\SimpleXMLElement {
		$raw = $zip->getFromName( $name );
		if ( false === $raw ) {
			return null;
		}
		$xml = simplexml_load_string( $raw, \SimpleXMLElement::class, LIBXML_NONET );

		return false === $xml ? null : $xml;
	}

	/**
	 * Text of a string item: plain `<t>` or rich-text runs `<r><t>`.
	 *
	 * @param \SimpleXMLElement|null $node `<si>` or `<is>`.
	 */
	private static function text( $node ): string {
		if ( ! $node instanceof \SimpleXMLElement ) {
			return '';
		}
		if ( isset( $node->t ) ) {
			return (string) $node->t;
		}
		$text = '';
		foreach ( $node->r as $run ) {
			$text .= (string) $run->t;
		}

		return $text;
	}

	/**
	 * 0-based column of a cell reference ("C7" → 2).
	 *
	 * @param string $ref Cell reference.
	 */
	public static function column( string $ref ): int {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$col     = 0;
		foreach ( str_split( (string) $letters ) as $ch ) {
			$col = $col * 26 + ( ord( $ch ) - 64 );
		}

		return max( 0, $col - 1 );
	}

	/**
	 * Sparse cells → list with '' gaps.
	 *
	 * @param array<int, string> $cells Column => text.
	 * @return array<int, string>
	 */
	private static function fill( array $cells ): array {
		$out = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
		foreach ( $cells as $col => $text ) {
			$out[ $col ] = $text;
		}

		return $out;
	}
}
