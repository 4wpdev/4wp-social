<?php
/**
 * Plugin Name:       4WP Social
 * Plugin URI:        https://4wp.dev/plugin/4wp-social/
 * Description:       Connect WordPress with social platforms: share (visitors), posting (cross-post videos to YouTube and TikTok via API) and feeds.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Author:            4wpdev
 * Author URI:        https://4wp.dev/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       4wp-social
 *
 * @package ForWP\Social
 */

defined( 'ABSPATH' ) || exit;

define( 'FORWP_SOCIAL_VERSION', '0.3.0' );
define( 'FORWP_SOCIAL_FILE', __FILE__ );
define( 'FORWP_SOCIAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_SOCIAL_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( FORWP_SOCIAL_PATH . 'vendor/autoload.php' ) ) {
	require_once FORWP_SOCIAL_PATH . 'vendor/autoload.php';
} else {
	require_once FORWP_SOCIAL_PATH . 'src/Autoload.php';
	ForWP\Social\Autoload::register();
}

register_activation_hook( __FILE__, array( 'ForWP\\Social\\Database\\Schema', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ForWP\\Social\\Modules\\Posting\\Queue', 'deactivate' ) );

ForWP\Social\Plugin::instance()->boot();
