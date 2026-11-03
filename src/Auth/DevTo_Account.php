<?php
/**
 * DEV.to brand account: personal API key (no OAuth app needed).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\DevTo_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Credentials_Store keeps the username (client_id) and API key (client_secret), Token_Store keeps the verified account.
 */
final class DevTo_Account {

	public const SLUG = 'dev.to';

	public const CONNECT_ACTION    = 'forwp_social_devto_connect';
	public const DISCONNECT_ACTION = 'forwp_social_devto_disconnect';
	public const TEST_ACTION       = 'forwp_social_devto_test';

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_post_' . self::CONNECT_ACTION, array( self::class, 'handle_connect' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( self::class, 'handle_disconnect' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( self::class, 'handle_test' ) );
	}

	/**
	 * Whether an API key was verified.
	 */
	public static function is_connected(): bool {
		return '' !== self::api_key() && '' !== (string) ( ( new Token_Store( self::SLUG ) )->get()['connected_at'] ?? '' );
	}

	/**
	 * Stored account (no secrets).
	 *
	 * @return array{account: string, connected_at: string}
	 */
	public static function details(): array {
		$stored = ( new Token_Store( self::SLUG ) )->get();

		return array(
			'account'      => (string) ( $stored['account'] ?? '' ),
			'connected_at' => (string) ( $stored['connected_at'] ?? '' ),
		);
	}

	/**
	 * Client for publishing.
	 */
	public static function client(): DevTo_Client {
		return new DevTo_Client( self::api_key() );
	}

	/**
	 * admin-post: verify the API key, then save it.
	 *
	 * @return void
	 */
	public static function handle_connect(): void {
		check_admin_referer( self::CONNECT_ACTION );
		self::require_admin();

		$key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( '' === $key ) {
			$key = self::api_key();
		}

		$me = ( new DevTo_Client( $key ) )->me();
		if ( is_wp_error( $me ) ) {
			self::redirect( array( 'forwp_social_error' => rawurlencode( $me->get_error_message() ) ) );
		}

		$account = self::account_name( $me );
		Credentials_Store::save( self::SLUG, $account, $key );
		self::remember( $account );
		self::redirect( array( 'forwp_social_notice' => 'connected' ) );
	}

	/**
	 * admin-post: read the account again with the saved API key.
	 *
	 * @return void
	 */
	public static function handle_test(): void {
		check_admin_referer( self::TEST_ACTION );
		self::require_admin();

		$me = self::client()->me();
		if ( is_wp_error( $me ) ) {
			self::redirect( array( 'forwp_social_error' => rawurlencode( $me->get_error_message() ) ) );
		}

		self::remember( self::account_name( $me ) );
		self::redirect( array( 'forwp_social_notice' => 'tested' ) );
	}

	/**
	 * admin-post: forget the API key and account.
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

	private static function api_key(): string {
		return Credentials_Store::get( self::SLUG )['client_secret'];
	}

	/**
	 * @param array<string, mixed> $me Account response.
	 */
	private static function account_name( array $me ): string {
		return (string) ( $me['username'] ?? ( $me['name'] ?? '' ) );
	}

	/**
	 * @param string $account Account name.
	 */
	private static function remember( string $account ): void {
		$store  = new Token_Store( self::SLUG );
		$stored = $store->get();
		$store->save(
			array(
				'account'      => $account,
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
