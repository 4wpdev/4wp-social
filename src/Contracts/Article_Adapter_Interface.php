<?php
/**
 * Outbound adapter for a platform that publishes text articles instead of video.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Marker: needs a mode "api" entry with a text file in meta.json and no video.
 */
interface Article_Adapter_Interface extends Platform_Adapter_Interface {
}
