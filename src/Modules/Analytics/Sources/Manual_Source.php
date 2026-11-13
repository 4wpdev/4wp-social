<?php
/**
 * Manual source: data comes only from uploaded CSV files (any platform without an API).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Contracts\Analytics_Import_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Csv;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base for manual platforms. Add one with forwp_social_analytics_sources:
 * `new Manual_Source( 'x.com', 'X', 'Upload the X analytics CSV.' )`.
 */
class Manual_Source implements Analytics_Import_Interface {

	/**
	 * Slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Note.
	 *
	 * @var string
	 */
	private $note;

	/**
	 * Manual source card.
	 *
	 * @param string $slug  Slug, as in the platform registry.
	 * @param string $label Label.
	 * @param string $note  What to export and upload.
	 */
	public function __construct( string $slug, string $label, string $note ) {
		$this->slug  = $slug;
		$this->label = $label;
		$this->note  = $note;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): string {
		return Analytics::MANUAL;
	}

	/**
	 * {@inheritDoc}
	 */
	public function note(): string {
		return $this->note;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		return new WP_Error( 'forwp_social_analytics_manual', __( 'This source is imported from a file.', '4wp-social' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions(): array {
		return array( 'csv' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Temporary file path.
	 * @param string $name Original file name.
	 */
	public function import( string $path, string $name ) {
		$text = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		$rows = Csv::parse( $text );
		if ( ! $rows ) {
			return new WP_Error( 'forwp_social_analytics_import', __( 'No rows found. The first row must be the header.', '4wp-social' ) );
		}

		return array(
			'file' => $name,
			'rows' => $rows,
		);
	}
}
