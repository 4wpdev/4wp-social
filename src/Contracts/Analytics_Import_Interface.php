<?php
/**
 * Analytics source filled by hand: the admin uploads an export file.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A source without an API (LinkedIn): data comes from an uploaded file.
 */
interface Analytics_Import_Interface extends Analytics_Source_Interface {

	/**
	 * File extensions the source accepts (csv).
	 *
	 * @return string[]
	 */
	public function extensions(): array;

	/**
	 * Parse an uploaded file into raw data.
	 *
	 * @param string $path Temporary file path.
	 * @param string $name Original file name.
	 * @return array<string, mixed>|WP_Error
	 */
	public function import( string $path, string $name );
}
