<?php
/**
 * Parse a platform article file from a meta.json package (e.g. dev.to.md).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

defined( 'ABSPATH' ) || exit;

/**
 * Same file format as the manual cross-posting of 4WP SEO Helper: optional front matter, optional "# Title", Markdown body.
 */
final class Article_Parser {

	/**
	 * @param string                $text         File contents.
	 * @param array<string, string> $replacements Placeholders such as {{original.url}} => value.
	 * @return array{title: string, body: string, fields: array<string, string>}
	 */
	public static function parse( string $text, array $replacements = array() ): array {
		$content = strtr( $text, $replacements );
		$fields  = array();

		if ( preg_match( '/\A\s*---\R(.*?)\R---\s*(?:\R|\z)/su', $content, $m ) ) {
			foreach ( preg_split( '/\R/u', $m[1] ) as $line ) {
				if ( preg_match( '/^\s*([A-Za-z0-9_\-]+)\s*:\s*(.*?)\s*$/u', $line, $kv ) ) {
					$value = trim( $kv[2], "\"'" );
					// Unresolved placeholder: leave the field out rather than publish "{{...}}".
					if ( '' !== $value && false === strpos( $value, '{{' ) ) {
						$fields[ strtolower( $kv[1] ) ] = $value;
					}
				}
			}
			$content = ltrim( (string) substr( $content, strlen( $m[0] ) ) );
		}

		$title = (string) ( $fields['title'] ?? '' );
		unset( $fields['title'] );

		// A leading "# Title" is the platform title, so it is not published twice.
		if ( preg_match( '/\A\s*#\s+(.+?)\s*(?:\R|\z)/u', $content, $m ) ) {
			$title   = '' !== $title ? $title : trim( $m[1] );
			$content = ltrim( (string) substr( $content, strlen( $m[0] ) ) );
		}

		return array(
			'title'  => $title,
			'body'   => trim( $content ),
			'fields' => $fields,
		);
	}
}
