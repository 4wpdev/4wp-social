<?php
/**
 * Placeholder for a source that is not built yet (Google Analytics, LinkedIn).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a card, never fetches.
 */
final class Planned_Source implements Analytics_Source_Interface {

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
	 * Placeholder card.
	 *
	 * @param string $slug  Slug.
	 * @param string $label Label.
	 * @param string $note  Note.
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
		return Analytics::PLANNED;
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
		return new WP_Error( 'forwp_social_analytics_planned', __( 'This source is planned.', '4wp-social' ) );
	}
}
