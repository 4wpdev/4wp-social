<?php
/**
 * Parse a platform copy file (e.g. 4wp-faq-overview-youtube.md).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the first fenced block under ## Title / ## Description / ## Caption / ## Tags.
 */
final class Platform_Copy_Parser {

	/**
	 * Recognized section names (heading text before any bracket, lowercased).
	 */
	private const SECTIONS = array( 'title', 'description', 'caption', 'tags' );

	/**
	 * Parse markdown.
	 *
	 * @param string $markdown File contents.
	 * @return array{title: string, description: string, caption: string, tags: array<int, string>}
	 */
	public static function parse( string $markdown ): array {
		$found   = array();
		$section = '';
		$in_code = false;
		$buffer  = array();

		foreach ( preg_split( '/\R/u', $markdown ) as $line ) {
			if ( preg_match( '/^\s*(```|~~~)/', $line ) ) {
				if ( $in_code ) {
					if ( '' !== $section && ! isset( $found[ $section ] ) ) {
						$found[ $section ] = trim( implode( "\n", $buffer ) );
					}
					$in_code = false;
					$buffer  = array();
				} else {
					$in_code = true;
				}
				continue;
			}

			if ( $in_code ) {
				$buffer[] = $line;
				continue;
			}

			if ( preg_match( '/^##\s+(.+)$/u', $line, $m ) ) {
				$name    = strtolower( trim( (string) preg_replace( '/[\(\[].*$/', '', $m[1] ) ) );
				$section = in_array( $name, self::SECTIONS, true ) ? $name : '';
			}
		}

		$tags = array();
		if ( ! empty( $found['tags'] ) ) {
			$tags = array_values( array_filter( array_map( 'trim', preg_split( '/[,\n]/', $found['tags'] ) ) ) );
		}

		return array(
			'title'       => (string) ( $found['title'] ?? '' ),
			'description' => (string) ( $found['description'] ?? '' ),
			'caption'     => (string) ( $found['caption'] ?? '' ),
			'tags'        => $tags,
		);
	}
}
