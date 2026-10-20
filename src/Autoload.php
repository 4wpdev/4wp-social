<?php
/**
 * PSR-4 autoloader fallback when Composer vendor is absent.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Registers autoload for ForWP\Social namespace.
 */
final class Autoload {

	/**
	 * Register spl autoload.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register(
			static function ( string $class_name ): void {
				$prefix = __NAMESPACE__ . '\\';
				if ( strncmp( $prefix, $class_name, strlen( $prefix ) ) !== 0 ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = FORWP_SOCIAL_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
