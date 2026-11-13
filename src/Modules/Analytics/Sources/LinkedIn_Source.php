<?php
/**
 * LinkedIn: no analytics API for us, the export file is uploaded by hand.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * A manual source. LinkedIn exports XLS/XLSX: save the sheet as CSV first (XLSX import later).
 */
final class LinkedIn_Source extends Manual_Source {

	/**
	 * LinkedIn card.
	 */
	public function __construct() {
		parent::__construct(
			'linkedin.com',
			__( 'LinkedIn', '4wp-social' ),
			__( 'Manual import: export analytics from LinkedIn, save the sheet as CSV and upload it here.', '4wp-social' )
		);
	}
}
