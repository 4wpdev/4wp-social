<?php
/**
 * Bluesky brand account: handle + app password (no OAuth app needed).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\Bluesky_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Credentials_Store keeps handle (client_id) and app password (client_secret), Token_Store keeps the verified account.
 */
final class Bluesky_Account {

	public const SLUG = 'bsky.app';

	public const CONNECT_ACTION    = 'forwp_social_bluesky_connect';
	public const DISCONNECT_ACTION = 'forwp_social_bluesky_disconnect';
	public const TEST_ACTION       = 'forwp_social_bluesky_test';

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_post_' . self::CONNECT_ACTION, array( self::class, 'handle_connect' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( self::class, 'handle_disconnect' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( self::class, 'handle_test' ) );
	}

	/**
	 * Whether an app password was verified.
	 */
	public static function is_connected(): bool {
		return Credentials_Store::has( self::SLUG ) && '' !== (string) ( ( new Token_Store( self::SLUG ) )->get()['did'] ?? '' );
	}

	/**
	 * Stored account (no secrets).
	 *
	 * @return array{handle: string, did: string, connected_at: string}
	 */
	public static function details(): array {
		$stored = ( new Token_Store( self::SLUG ) )->get();

		return array(
			'handle'       => (string) ( $stored['account'] ?? '' ),
			'did'          => (string) ( $stored['did'] ?? '' ),
			'connected_at' => (string) ( $stored['connected_at'] ?? '' ),
		);
	}

	/**
	 * Logged-in client for publishing.
	 *
	 * @return Bluesky_Client|\WP_Error
	 */
	public static function client() {
		$creds  = Credentials_Store::get( self::SLUG );
		$client = new Bluesky_Client();
		$login  = $client->login( $creds['client_id'], $creds['client_secret'] );

		return is_wp_error( $login ) ? $login : $client;
	}

	/**
	 * admin-post: verify handle + app password, then save them.
	 *
	 * @return void
	 */
	public static function handle_connect(): void {
		check_admin_referer( self::CONNECT_ACTION );
		self::require_admin();

		$handle   = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
		$password = isset( $_POST['app_password'] ) ? sanitize_text_field( wp_unslash( $_POST['app_password'] ) ) : '';
		if ( '' === $password ) {
			$password = Credentials_Store::get( self::SLUG )['client_secret'];
		}

		$login = ( new Bluesky_Client() )->login( $handle, $password );
		if ( is_wp_error( $login ) ) {
			self::redirect( array( 'forwp_social_error' => rawurlencode( $login->get_error_message() ) ) );
		}

		Credentials_Store::save( self::SLUG, $handle, $password );
		self::remember( $login );
		self::redirect( array( 'forwp_social_notice' => 'connected' ) );
	}

	/**
	 * admin-post: log in again with the saved app password.
	 *
	 * @return void
	 */
	public static function handle_test(): void {
		check_admin_referer( self::TEST_ACTION );
		self::require_admin();

		$creds = Credentials_Store::get( self::SLUG );
		$login = ( new Bluesky_Client() )->login( $creds['client_id'], $creds['client_secret'] );
		if ( is_wp_error( $login ) ) {
			self::redirect( array( 'forwp_social_error' => rawurlencode( $login->get_error_message() ) ) );
		}

		self::remember( $login );
		self::redirect( array( 'forwp_social_notice' => 'tested' ) );
	}

	/**
	 * admin-post: forget the app password and account.
	 *
	 * @return void
	 */
	public static function handle_disconnect(): void {
		check_admin_referer( self::DISCONNECT_ACTION );
		self::require_admin();

		$all = Crypto::read_option( Credentials_Store::OPTION_KEY );
		unset( $all[ self::SLUG ] );
		Crypto::save_option( Credentials_Store::OPTION_KEY, $all );
		( new Token_Store( self::SLUG ) )->delete();

		self::redirect( array( 'forwp_social_notice' => 'disconnected' ) );
	}

	/**
	 * Nonce URL for test / disconnect.
	 *
	 * @param string $action TEST_ACTION | DISCONNECT_ACTION.
	 */
	public static function action_url( string $action ): string {
		return wp_nonce_url( add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) ), $action );
	}

	/**
	 * @param array{did: string, handle: string} $login Session account.
	 */
	private static function remember( array $login ): void {
		$store  = new Token_Store( self::SLUG );
		$stored = $store->get();
		$store->save(
			array(
				'account'      => $login['handle'],
				'did'          => $login['did'],
				'connected_at' => (string) ( $stored['connected_at'] ?? gmdate( 'c' ) ),
				'tested_at'    => gmdate( 'c' ),
			)
		);
	}

	/**
	 * @param array<string, string> $args Query args.
	 * @return void
	 */
	private static function redirect( array $args ): void {
		wp_safe_redirect( OAuth_Controller::instance()->accounts_url( $args, self::SLUG ) );
		exit;
	}

	/**
	 * @return void
	 */
	private static function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change 4WP Social settings.', '4wp-social' ) );
		}
	}
}
