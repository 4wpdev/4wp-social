<?php
/**
 * SRT / VTT captions → plain transcript.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Video;

defined( 'ABSPATH' ) || exit;

/**
 * Drops cue numbers, timings and markup; joins cue lines into one text.
 */
final class Srt {

	/**
	 * @param string $captions SRT or VTT contents.
	 */
	public static function to_text( string $captions ): string {
		$captions = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $captions );
		$lines    = array();

		foreach ( preg_split( '/\R/u', $captions ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 'WEBVTT' === $line || ctype_digit( $line ) || false !== strpos( $line, '-->' ) ) {
				continue;
			}

			$line = trim( (string) preg_replace( '/<[^>]*>|\{[^}]*\}/', '', $line ) );
			if ( '' !== $line && end( $lines ) !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( ' ', $lines );
	}
}
