<?php
/**
 * Minimal WordPress stubs for unit tests (no WP test suite), same approach as 4WP Drive.
 *
 * @package ForWP\Social\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Intentional core function stubs for PHPUnit.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Reset in-memory state between tests.
 */
function forwp_social_tests_reset(): void {
	$GLOBALS['forwp_social_test_options'] = array();
	$GLOBALS['forwp_social_test_filters'] = array();
}

forwp_social_tests_reset();

function get_option( $option, $default = false ) {
	return array_key_exists( $option, $GLOBALS['forwp_social_test_options'] ) ? $GLOBALS['forwp_social_test_options'][ $option ] : $default;
}

function update_option( $option, $value, $autoload = null ) {
	$GLOBALS['forwp_social_test_options'][ $option ] = $value;
	return true;
}

function delete_option( $option ) {
	unset( $GLOBALS['forwp_social_test_options'][ $option ] );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['forwp_social_test_filters'][ $hook ][] = $callback;
	return true;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['forwp_social_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['forwp_social_test_filters'][ $hook ] ?? array() as $callback ) {
		$callback( ...$args );
	}
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $str ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( (string) $title ) ), '-' );
}

function sanitize_textarea_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options );
}

function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}

function wp_delete_file( $file ) {
	if ( file_exists( $file ) ) {
		unlink( $file );
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}
