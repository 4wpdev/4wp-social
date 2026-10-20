<?php
/**
 * Where a publishing job takes its media and texts from.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

use ForWP\Social\Core\Media_Package;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Implemented by Package_Reader (meta.json via 4WP Drive) and Post_Reader (the post itself).
 */
interface Media_Source_Interface {

	/**
	 * Build the package for one platform.
	 *
	 * @param int                  $post_id    Post id.
	 * @param string               $platform   Platform key.
	 * @param bool                 $with_media Resolve local media paths (false = metadata only, for polling).
	 * @param array<string, mixed> $options    Job options.
	 * @return Media_Package|WP_Error
	 */
	public function build( int $post_id, string $platform, bool $with_media, array $options = array() );
}
