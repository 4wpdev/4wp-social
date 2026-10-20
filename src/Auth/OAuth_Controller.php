<?php
/**
 * Connect / disconnect brand accounts and hand out fresh access tokens.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Authorization-code flow for administrators (state in a transient, like 4WP Drive).
 */
final class OAuth_Controller {

	private const STATE_PREFIX = 'forwp_social_oauth_';

	/**
	 * Redirect URI overrides for local development (platform => URI).
	 */
	public const REDIRECT_OPTION = 'forwp_social_oauth_redirects';

	public const START_ACTION      = 'forwp_social_oauth_start';
	public const DISCONNECT_ACTION = 'forwp_social_oauth_disconnect';
	public const TEST_ACTION       = 'forwp_social_oauth_test';

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var array<string, OAuth_Provider_Interface>|null
	 */
	private $providers = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::START_ACTION, array( $this, 'handle_start' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'handle_test' ) );
	}

	/**
	 * OAuth providers keyed by platform.
	 *
	 * @return array<string, OAuth_Provider_Interface>
	 */
	public function providers(): array {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		$map = array(
			Google_OAuth_Provider::SLUG => new Google_OAuth_Provider(),
			TikTok_OAuth_Provider::SLUG => new TikTok_OAuth_Provider(),
		);

		/**
		 * Filter OAuth providers for brand accounts.
		 *
		 * @param array<string, OAuth_Provider_Interface> $map Providers keyed by platform.
		 */
		$filtered        = apply_filters( 'forwp_social_oauth_providers', $map );
		$this->providers = array();
		foreach ( is_array( $filtered ) ? $filtered : $map as $slug => $provider ) {
			if ( $provider instanceof OAuth_Provider_Interface ) {
				$this->providers[ (string) $slug ] = $provider;
			}
		}

		return $this->providers;
	}

	/**
	 * @param string $platform Platform key.
	 */
	public function provider( string $platform ): ?OAuth_Provider_Interface {
		return $this->providers()[ $platform ] ?? null;
	}

	/**
	 * Callback URL registered in the platform console.
	 *
	 * @param string $platform Platform key.
	 */
	public function redirect_uri( string $platform ): string {
		$custom = self::redirect_overrides()[ $platform ] ?? '';

		return '' !== $custom ? $custom : $this->default_redirect_uri( $platform );
	}

	/**
	 * Callback on the site address (what production uses).
	 *
	 * @param string $platform Platform key.
	 */
	public function default_redirect_uri( string $platform ): string {
		return rest_url( 'forwp-social/v1/oauth/callback/' . $platform );
	}

	/**
	 * Saved redirect overrides (development mode), keyed by platform.
	 *
	 * @return array<string, string>
	 */
	public static function redirect_overrides(): array {
		$map = get_option( self::REDIRECT_OPTION, array() );

		return is_array( $map ) ? array_map( 'strval', $map ) : array();
	}

	/**
	 * Save or clear (empty) a redirect override.
	 *
	 * @param string $platform Platform key.
	 * @param string $uri      Redirect URI.
	 */
	public static function save_redirect_override( string $platform, string $uri ): void {
		$map = self::redirect_overrides();
		$uri = trim( $uri );
		if ( '' === $uri ) {
			unset( $map[ $platform ] );
		} else {
			$map[ $platform ] = esc_url_raw( $uri );
		}
		update_option( self::REDIRECT_OPTION, $map, false );
	}

	/**
	 * Whether the site runs on a host OAuth providers reject (.local, .test, .localhost) — same rule as 4WP Drive.
	 */
	public static function is_local_site(): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return '' !== $host && ! in_array( $host, array( 'localhost', '127.0.0.1' ), true )
			&& (bool) preg_match( '/\.(local|localhost|test|invalid)$/i', $host );
	}

	/**
	 * Loopback callback: 127.0.0.1 + the Local port instead of the site host (as 4WP Drive does).
	 *
	 * @param string $platform Platform key.
	 */
	public function suggested_loopback_uri( string $platform ): string {
		if ( ! self::is_local_site() ) {
			return '';
		}

		$parts  = wp_parse_url( $this->default_redirect_uri( $platform ) );
		$parts  = is_array( $parts ) ? $parts : array();
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : self::detect_local_port();
		$suffix = $port > 0 && ! in_array( $port, array( 80, 443 ), true ) ? ':' . $port : '';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		// Local serves the per-site port over plain http; Google allows http only for loopback.
		return 'http://127.0.0.1' . $suffix . ( $parts['path'] ?? '/' ) . $query;
	}

	/**
	 * Non-standard port Local serves the site on.
	 */
	private static function detect_local_port(): int {
		$port = isset( $_SERVER['SERVER_PORT'] ) ? (int) $_SERVER['SERVER_PORT'] : 0;
		if ( $port > 0 && ! in_array( $port, array( 80, 443 ), true ) ) {
			return $port;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( false !== strpos( $host, ':' ) ) {
			return (int) substr( $host, (int) strrpos( $host, ':' ) + 1 );
		}

		return 0;
	}

	/**
	 * @param string $platform Platform key.
	 */
	public function is_connected( string $platform ): bool {
		return ( new Token_Store( $platform ) )->is_connected();
	}

	/**
	 * Connected account name, empty when unknown.
	 *
	 * @param string $platform Platform key.
	 */
	public function account_label( string $platform ): string {
		return (string) ( ( new Token_Store( $platform ) )->get()['account'] ?? '' );
	}

	/**
	 * Authorization URL with a fresh state.
	 *
	 * @param string $platform Platform key.
	 * @return string|WP_Error
	 */
	public function get_auth_url( string $platform ) {
		$provider = $this->provider( $platform );
		if ( ! $provider ) {
			return new WP_Error( 'forwp_social_no_provider', __( 'Unknown platform.', '4wp-social' ) );
		}
		if ( ! Credentials_Store::has( $platform ) ) {
			return new WP_Error( 'forwp_social_no_client', __( 'Save the app credentials for this platform first.', '4wp-social' ) );
		}

		$state = wp_generate_password( 32, false );
		set_transient(
			self::STATE_PREFIX . $state,
			array(
				'user_id'  => get_current_user_id(),
				'platform' => $platform,
			),
			10 * MINUTE_IN_SECONDS
		);

		return $provider->get_auth_url( Credentials_Store::get( $platform )['client_id'], $this->redirect_uri( $platform ), $state );
	}

	/**
	 * admin-post: start the flow.
	 *
	 * @return void
	 */
	public function handle_start(): void {
		$platform = $this->request_platform();
		check_admin_referer( self::START_ACTION . '_' . $platform );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect accounts.', '4wp-social' ) );
		}

		$url = $this->get_auth_url( $platform );
		if ( is_wp_error( $url ) ) {
			$this->redirect_accounts( array( 'forwp_social_error' => rawurlencode( $url->get_error_message() ) ) );
		}

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External OAuth provider.
		exit;
	}

	/**
	 * admin-post: forget tokens.
	 *
	 * @return void
	 */
	public function handle_disconnect(): void {
		$platform = $this->request_platform();
		check_admin_referer( self::DISCONNECT_ACTION . '_' . $platform );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to disconnect accounts.', '4wp-social' ) );
		}

		$store    = new Token_Store( $platform );
		$tokens   = $store->get();
		$provider = $this->provider( $platform );
		$token    = (string) ( $tokens['refresh_token'] ?? $tokens['access_token'] ?? '' );
		$revoked  = ( $provider instanceof Revocable_Provider_Interface && '' !== $token ) ? $provider->revoke( $token ) : true;

		// Local tokens go regardless: the site must forget them even if the platform is unreachable.
		$store->delete();

		if ( is_wp_error( $revoked ) ) {
			$this->redirect_accounts( array( 'forwp_social_error' => rawurlencode( $revoked->get_error_message() ) ), $platform );
		}
		$this->redirect_accounts( array( 'forwp_social_notice' => 'disconnected' ), $platform );
	}

	/**
	 * admin-post: refresh the token and read the account name.
	 *
	 * @return void
	 */
	public function handle_test(): void {
		$platform = $this->request_platform();
		check_admin_referer( self::TEST_ACTION . '_' . $platform );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to test accounts.', '4wp-social' ) );
		}

		$token = $this->get_access_token( $platform );
		if ( is_wp_error( $token ) ) {
			$this->redirect_accounts( array( 'forwp_social_error' => rawurlencode( $token->get_error_message() ) ), $platform );
		}

		$provider = $this->provider( $platform );
		$label    = $provider ? $provider->get_account_label( $token ) : '';
		if ( '' !== $label ) {
			$store              = new Token_Store( $platform );
			$tokens             = $store->get();
			$tokens['account']  = $label;
			$tokens['tested_at'] = gmdate( 'c' );
			$store->save( $tokens );
		}

		$this->redirect_accounts( array( 'forwp_social_notice' => 'tested' ), $platform );
	}

	/**
	 * Stored token details for the Accounts screen (no secrets).
	 *
	 * @param string $platform Platform key.
	 * @return array{connected: bool, account: string, connected_at: string, expires_at: int, refresh_expires_at: int, scope: string}
	 */
	public function connection_details( string $platform ): array {
		$tokens = ( new Token_Store( $platform ) )->get();

		return array(
			'connected'          => '' !== (string) ( $tokens['refresh_token'] ?? '' ),
			'account'            => (string) ( $tokens['account'] ?? '' ),
			'connected_at'       => (string) ( $tokens['connected_at'] ?? '' ),
			'expires_at'         => (int) ( $tokens['expires_at'] ?? 0 ),
			'refresh_expires_at' => (int) ( $tokens['refresh_expires_at'] ?? 0 ),
			'scope'              => (string) ( $tokens['scope'] ?? '' ),
		);
	}

	/**
	 * Admin-post URL for an account action.
	 *
	 * @param string $action   START_ACTION | DISCONNECT_ACTION | TEST_ACTION.
	 * @param string $platform Platform key.
	 */
	public function action_url( string $action, string $platform ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => $action,
					'platform' => $platform,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $platform
		);
	}

	/**
	 * Finish the flow (REST callback). Returns the admin URL to send the browser to.
	 *
	 * @param string $platform Platform key.
	 * @param string $code     Authorization code.
	 * @param string $state    State from the provider.
	 * @param string $error    Error from the provider.
	 */
	public function complete( string $platform, string $code, string $state, string $error ): string {
		if ( '' !== $error ) {
			return $this->accounts_url( array( 'forwp_social_error' => rawurlencode( $error ) ), $platform );
		}

		$pending = '' !== $state ? get_transient( self::STATE_PREFIX . $state ) : false;
		delete_transient( self::STATE_PREFIX . $state );

		$provider = $this->provider( $platform );
		if ( ! is_array( $pending ) || ( $pending['platform'] ?? '' ) !== $platform || ! $provider || '' === $code ) {
			return $this->accounts_url( array( 'forwp_social_error' => rawurlencode( __( 'OAuth state is invalid or expired. Try again.', '4wp-social' ) ) ) );
		}
		if ( ! user_can( (int) ( $pending['user_id'] ?? 0 ), 'manage_options' ) ) {
			return $this->accounts_url( array( 'forwp_social_error' => rawurlencode( __( 'The user who started the connection may not manage options.', '4wp-social' ) ) ) );
		}

		$tokens = $provider->exchange_code( Credentials_Store::get( $platform ), $code, $this->redirect_uri( $platform ) );
		if ( is_wp_error( $tokens ) ) {
			return $this->accounts_url( array( 'forwp_social_error' => rawurlencode( $tokens->get_error_message() ) ), $platform );
		}

		$tokens['account']      = $provider->get_account_label( (string) $tokens['access_token'] );
		$tokens['connected_at'] = gmdate( 'c' );
		( new Token_Store( $platform ) )->save( $tokens );

		return $this->accounts_url( array( 'forwp_social_notice' => 'connected' ), $platform );
	}

	/**
	 * Valid access token, refreshed when it expires within a minute.
	 *
	 * @param string $platform Platform key.
	 * @return string|WP_Error
	 */
	public function get_access_token( string $platform ) {
		$store    = new Token_Store( $platform );
		$tokens   = $store->get();
		$provider = $this->provider( $platform );

		if ( ! $provider || empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'forwp_social_not_connected', __( 'The account for this platform is not connected.', '4wp-social' ) );
		}

		if ( ! empty( $tokens['access_token'] ) && (int) ( $tokens['expires_at'] ?? 0 ) > time() + 60 ) {
			return (string) $tokens['access_token'];
		}

		$fresh = $provider->refresh( Credentials_Store::get( $platform ), (string) $tokens['refresh_token'] );
		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}

		$store->save( self::merge_tokens( $tokens, $fresh ) );

		return (string) $fresh['access_token'];
	}

	/**
	 * Merge refreshed tokens: empty values never overwrite stored ones (Google omits refresh_token).
	 *
	 * @param array<string, mixed> $stored Stored tokens.
	 * @param array<string, mixed> $fresh  Refreshed tokens.
	 * @return array<string, mixed>
	 */
	public static function merge_tokens( array $stored, array $fresh ): array {
		foreach ( $fresh as $key => $value ) {
			if ( '' !== $value && null !== $value && 0 !== $value ) {
				$stored[ $key ] = $value;
			}
		}

		return $stored;
	}

	/**
	 * Admin URL of the Accounts tab.
	 *
	 * @param array<string, string> $args     Extra query args.
	 * @param string                $platform Platform the result belongs to: shown inside its box, not on top.
	 */
	public function accounts_url( array $args = array(), string $platform = '' ): string {
		if ( '' !== $platform ) {
			$args['forwp_social_platform'] = $platform;
		}
		$url = add_query_arg(
			array_merge(
				array(
					'page' => 'forwp-social',
					'tab'  => 'accounts',
				),
				$args
			),
			admin_url( 'admin.php' )
		);

		return '' !== $platform ? $url . '#' . self::connect_anchor( $platform ) : $url;
	}

	/**
	 * HTML id of a platform's "Connect the account" step.
	 *
	 * @param string $platform Platform key.
	 */
	public static function connect_anchor( string $platform ): string {
		return 'forwp-social-' . sanitize_html_class( str_replace( '.', '-', $platform ) ) . '-connect';
	}

	/**
	 * Redirect to the Accounts tab and stop.
	 *
	 * @param array<string, string> $args     Extra query args.
	 * @param string                $platform Platform the result belongs to.
	 * @return void
	 */
	private function redirect_accounts( array $args, string $platform = '' ): void {
		wp_safe_redirect( $this->accounts_url( $args, $platform ) );
		exit;
	}

	/**
	 * Platform from the request, limited to known providers.
	 */
	private function request_platform(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked by caller with this value.
		$platform = isset( $_REQUEST['platform'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['platform'] ) ) : '';

		return isset( $this->providers()[ $platform ] ) ? $platform : '';
	}
}
