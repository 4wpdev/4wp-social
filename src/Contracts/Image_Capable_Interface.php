<?php
/**
 * An adapter that can publish an image when the package has no video (Pinterest image pin).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Marker: Package_Reader / Post_Reader do not require a video; the cover (or featured image) is enough.
 */
interface Image_Capable_Interface extends Platform_Adapter_Interface {
}
