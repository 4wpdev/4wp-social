<?php
/**
 * CSV text → rows keyed by the header row.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Small CSV reader for manual analytics imports.
 */
final class Csv {

	/**
	 * Row limit per file, enough for a year of daily rows.
	 */
	public const MAX_ROWS = 5000;

	/**
	 * Parse CSV. Handles a UTF-8 BOM and `,` or `;` separators (picked from the header row).
	 *
	 * @param string $text CSV text.
	 * @return array<int, array<string, string>>
	 */
	public static function parse( string $text ): array {
		$text  = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$lines = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		if ( ! $lines || '' === $lines[0] ) {
			return array();
		}

		$sep    = substr_count( $lines[0], ';' ) > substr_count( $lines[0], ',' ) ? ';' : ',';
		$header = array_map( 'trim', str_getcsv( (string) array_shift( $lines ), $sep ) );
		$rows   = array();

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line, $sep );
			$row   = array();
			foreach ( $header as $i => $key ) {
				$row[ '' !== $key ? $key : 'col_' . $i ] = trim( (string) ( $cells[ $i ] ?? '' ) );
			}
			$rows[] = $row;
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
		}

		return $rows;
	}
}
