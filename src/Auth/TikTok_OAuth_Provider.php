<?php
/**
 * TikTok Login Kit (OAuth v2) for the brand account.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\Http;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * TikTok OAuth: user.info.basic + video.publish (Content Posting API, Direct Post) always;
 * Display API scopes (analytics) only when turned on in the keys form.
 */
final class TikTok_OAuth_Provider implements OAuth_Provider_Interface {

	public const SLUG = 'tiktok.com';

	private const AUTH_URL  = 'https://www.tiktok.com/v2/auth/authorize/';
	private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

	/** Scopes requested on every connect. */
	public const REQUIRED_SCOPES = array( 'user.info.basic', 'video.publish' );

	/** Analytics scopes (Display API): requested only when turned on, TikTok rejects scopes the app lacks. */
	public const OPTIONAL_SCOPES = array( 'user.info.profile', 'user.info.stats', 'video.list' );

	/**
	 * Credential field that turns an optional scope on.
	 *
	 * @param string $scope Scope.
	 */
	public static function scope_field( string $scope ): string {
		return 'scope_' . str_replace( '.', '_', $scope );
	}

	/**
	 * Scopes the next connect will request: required + optional turned on in the keys form.
	 *
	 * @return array<string>
	 */
	public static function requested_scopes(): array {
		$scopes = self::REQUIRED_SCOPES;
		foreach ( self::OPTIONAL_SCOPES as $scope ) {
			if ( '1' === Credentials_Store::extra( self::SLUG, self::scope_field( $scope ) ) ) {
				$scopes[] = $scope;
			}
		}

		return OAuth_Controller::scopes( self::SLUG, $scopes );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return self::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return 'TikTok';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_credential_labels(): array {
		$fields = array();
		foreach ( self::OPTIONAL_SCOPES as $scope ) {
			$fields[ self::scope_field( $scope ) ] = array(
				/* translators: %s: TikTok scope, e.g. video.list. */
				'label' => sprintf( __( 'Request %s (analytics)', '4wp-social' ), $scope ),
				'type'  => 'checkbox',
			);
		}
		$fields[ self::scope_field( 'video.list' ) ]['help'] = __( 'Turn on only the scopes listed in the TikTok app (Production → Scopes, or Sandbox for sandbox keys). A scope the app lacks makes TikTok refuse the login with a "scope" error. Then Reconnect.', '4wp-social' );

		return array(
			'client_id'     => __( 'Client key', '4wp-social' ),
			'client_secret' => __( 'Client secret', '4wp-social' ),
			'console_url'   => 'https://developers.tiktok.com/apps/',
			'extra_fields'  => $fields,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_setup_guide(): array {
		return array(
			'console_url'           => 'https://developers.tiktok.com/apps/',
			'console_label'         => __( 'Open TikTok for Developers', '4wp-social' ),
			'account_hint'          => __( 'Sign in with the TikTok account the videos should appear on.', '4wp-social' ),
			'steps'                 => array(
				__( 'In TikTok for Developers → <strong>Manage apps</strong> → <strong>Connect an app</strong>.', '4wp-social' ),
				__( 'App details: icon, name, category, Terms of Service and Privacy Policy URLs, platform <strong>Web</strong> with your site URL.', '4wp-social' ),
				__( '<strong>Add products</strong>: <strong>Login Kit</strong>, <strong>Content Posting API</strong> and <strong>Display API</strong> (analytics). In Content Posting API turn on <strong>Direct Post</strong>.', '4wp-social' ),
				__( '<strong>Scopes</strong>: <code>user.info.basic</code>, <code>video.publish</code>. For analytics also <code>user.info.profile</code>, <code>user.info.stats</code>, <code>video.list</code> — in production they need TikTok review; turn them on in step 2 only after they appear in the app.', '4wp-social' ),
				__( 'Login Kit → <strong>Redirect URI</strong> → Web → paste the Redirect URI below (https, no query string).', '4wp-social' ),
				__( 'For testing: open the <strong>Sandbox</strong> tab, create a sandbox and add your TikTok account as a <strong>Target user</strong>. Use the sandbox keys until the app is approved.', '4wp-social' ),
				__( 'Copy the <strong>Client key</strong> and <strong>Client secret</strong> into step 2.', '4wp-social' ),
			),
			'notes'                 => array(
				__( 'Until TikTok audits the app, every post is private (SELF_ONLY). The plugin falls back to private automatically and says so in the queue. Submit the app for review when testing is done.', '4wp-social' ),
				__( 'TikTok allows 6 upload starts per minute per account.', '4wp-social' ),
			),
			'public_https_required' => true,
			'loopback_allowed'      => false,
			'query_allowed'         => false,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $client_id    Client key.
	 * @param string $redirect_uri Redirect URI.
	 * @param string $state        OAuth state.
	 */
	public function get_auth_url( string $client_id, string $redirect_uri, string $state ): string {
		$params = array(
			'client_key'    => $client_id,
			'scope'         => implode( ',', self::requested_scopes() ),
			'response_type' => 'code',
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $credentials  Client key and secret.
	 * @param string                $code         Authorization code.
	 * @param string                $redirect_uri Redirect URI.
	 */
	public function exchange_code( array $credentials, string $code, string $redirect_uri ) {
		return $this->token_request(
			array(
				'client_key'    => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $redirect_uri,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $credentials   Client key and secret.
	 * @param string                $refresh_token Refresh token.
	 */
	public function refresh( array $credentials, string $refresh_token ) {
		return $this->token_request(
			array(
				'client_key'    => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $access_token Access token.
	 */
	public function get_account_label( string $access_token ): string {
		$res = Http::json(
			'POST',
			'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',
			array(),
			array( 'Authorization' => 'Bearer ' . $access_token )
		);
		if ( is_wp_error( $res ) || 200 !== $res['code'] ) {
			return '';
		}

		$username = (string) ( $res['json']['data']['creator_username'] ?? '' );

		return '' !== $username ? '@' . $username : (string) ( $res['json']['data']['creator_nickname'] ?? '' );
	}

	/**
	 * POST to the token endpoint and normalize.
	 *
	 * @param array<string, string> $body Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ) {
		$res = Http::request(
			'POST',
			self::TOKEN_URL,
			array(
				'headers' => array(
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Cache-Control' => 'no-cache',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] || empty( $res['json']['access_token'] ) ) {
			return new WP_Error( 'forwp_social_tiktok_token', Http::error_message( $res, __( 'TikTok token request failed.', '4wp-social' ) ) );
		}

		$json = $res['json'];

		return array(
			'access_token'       => (string) $json['access_token'],
			'refresh_token'      => (string) ( $json['refresh_token'] ?? '' ),
			'expires_at'         => time() + (int) ( $json['expires_in'] ?? 86400 ),
			'refresh_expires_at' => time() + (int) ( $json['refresh_expires_in'] ?? 0 ),
			'open_id'            => (string) ( $json['open_id'] ?? '' ),
			'scope'              => (string) ( $json['scope'] ?? '' ),
		);
	}
}
