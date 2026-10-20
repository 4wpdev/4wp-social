<?php
/**
 * OAuth 2.0 provider for a brand account on one platform.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Authorization-code flow details that differ between platforms.
 */
interface OAuth_Provider_Interface {

	/**
	 * Platform key (youtube.com, tiktok.com).
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * Labels for the two credential fields.
	 *
	 * @return array{client_id: string, client_secret: string, console_url: string}
	 */
	public function get_credential_labels(): array;

	/**
	 * Setup guide for the Accounts screen: where to create the app and what to fill in.
	 *
	 * @return array{
	 *     console_url: string,
	 *     console_label: string,
	 *     account_hint: string,
	 *     steps: array<int, string>,
	 *     notes: array<int, string>,
	 *     public_https_required: bool,
	 *     loopback_allowed: bool,
	 *     query_allowed: bool
	 * }
	 */
	public function get_setup_guide(): array;

	/**
	 * Authorization URL.
	 *
	 * @param string $client_id    Client ID / key.
	 * @param string $redirect_uri Callback URL.
	 * @param string $state        CSRF state.
	 */
	public function get_auth_url( string $client_id, string $redirect_uri, string $state ): string;

	/**
	 * Exchange the code for tokens.
	 *
	 * @param array{client_id: string, client_secret: string} $credentials  App credentials.
	 * @param string                                          $code         Authorization code.
	 * @param string                                          $redirect_uri Callback URL.
	 * @return array<string, mixed>|WP_Error Normalized: access_token, refresh_token, expires_at.
	 */
	public function exchange_code( array $credentials, string $code, string $redirect_uri );

	/**
	 * Refresh an access token.
	 *
	 * @param array{client_id: string, client_secret: string} $credentials   App credentials.
	 * @param string                                          $refresh_token Refresh token.
	 * @return array<string, mixed>|WP_Error Normalized tokens (refresh_token may be absent).
	 */
	public function refresh( array $credentials, string $refresh_token );

	/**
	 * Name of the connected account for the admin screen (channel, @username).
	 *
	 * @param string $access_token Access token.
	 */
	public function get_account_label( string $access_token ): string;
}
