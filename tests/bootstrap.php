<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ForWP\Social\Tests
 */

require_once __DIR__ . '/wp-stubs.php';

$forwp_social_plugin_root = dirname( __DIR__ );

if ( ! defined( 'FORWP_SOCIAL_PATH' ) ) {
	define( 'FORWP_SOCIAL_PATH', $forwp_social_plugin_root . '/' );
}

if ( file_exists( $forwp_social_plugin_root . '/vendor/autoload.php' ) ) {
	require_once $forwp_social_plugin_root . '/vendor/autoload.php';
} else {
	require_once $forwp_social_plugin_root . '/src/Autoload.php';
	ForWP\Social\Autoload::register();
}
