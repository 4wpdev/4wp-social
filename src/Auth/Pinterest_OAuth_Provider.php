<?php
/**
 * Pinterest API v5 OAuth 2.0 for the brand account.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\Http;
use ForWP\Social\Api\Pinterest_Client;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Scopes: boards:read, pins:read, pins:write, user_accounts:read.
 * Token endpoint uses HTTP Basic auth (client_id:client_secret).
 */
final class Pinterest_OAuth_Provider implements OAuth_Provider_Interface {

	public const SLUG = 'pinterest.com';

	private const AUTH_URL  = 'https://www.pinterest.com/oauth/';
	private const TOKEN_URL = 'https://api.pinterest.com/v5/oauth/token';

	private const SCOPES = array( 'boards:read', 'pins:read', 'pins:write', 'user_accounts:read' );

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Pinterest';
	}

	public function get_credential_labels(): array {
		return array(
			'client_id'     => __( 'App ID', '4wp-social' ),
			'client_secret' => __( 'App secret key', '4wp-social' ),
			'console_url'   => 'https://developers.pinterest.com/apps/',
			'extra_fields'  => array(
				'board'   => array(
					'label' => __( 'Board (name or ID)', '4wp-social' ),
					'type'  => 'text',
					'help'  => __( 'Pins go to this board. Empty: the first board of the account. meta.json "board" wins.', '4wp-social' ),
				),
				'sandbox' => array(
					'label' => __( 'Trial access: post to the sandbox', '4wp-social' ),
					'type'  => 'checkbox',
					'help'  => __( 'Apps with Trial access can create pins only in the Pinterest sandbox (api-sandbox.pinterest.com). Turn off after Standard access is approved.', '4wp-social' ),
				),
			),
		);
	}

	public function get_setup_guide(): array {
		return array(
			'console_url'           => 'https://developers.pinterest.com/apps/',
			'console_label'         => __( 'Open Pinterest Developers', '4wp-social' ),
			'account_hint'          => __( 'Sign in with the Pinterest business account the pins should appear on.', '4wp-social' ),
			'steps'                 => array(
				__( 'Use a Pinterest <strong>business</strong> account (Settings → Account management → convert to business).', '4wp-social' ),
				__( 'In Pinterest Developers → <strong>My apps</strong> → <strong>Connect app</strong>: name, description, website, privacy policy URL.', '4wp-social' ),
				__( 'Wait for <strong>Trial access</strong> (usually quick). Until <strong>Standard access</strong> is approved, turn on “post to the sandbox” in step 2.', '4wp-social' ),
				__( 'App → <strong>Configure</strong> → <strong>Redirect URIs</strong> → add the Redirect URI below exactly.', '4wp-social' ),
				__( 'Copy the <strong>App ID</strong> and <strong>App secret key</strong> into step 2.', '4wp-social' ),
				__( 'Create at least one board on the account (or set its name in step 2).', '4wp-social' ),
			),
			'notes'                 => array(
				__( 'Video pins: the file is uploaded to Pinterest, processed, then the pin is created (the queue checks the status). Image pins are created at once from the cover or the featured image.', '4wp-social' ),
				__( 'meta.json "pin_type": "image" makes an image pin even when the package has a video.', '4wp-social' ),
			),
			'public_https_required' => false,
			'loopback_allowed'      => true,
			'query_allowed'         => false,
		);
	}

	public function get_auth_url( string $client_id, string $redirect_uri, string $state ): string {
		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ',', OAuth_Controller::scopes( self::SLUG, self::SCOPES ) ),
			'state'         => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	public function exchange_code( array $credentials, string $code, string $redirect_uri ) {
		return $this->token_request(
			$credentials,
			array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $redirect_uri,
			)
		);
	}

	public function refresh( array $credentials, string $refresh_token ) {
		return $this->token_request(
			$credentials,
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			)
		);
	}

	public function get_account_label( string $access_token ): string {
		$account = ( new Pinterest_Client( $access_token, false ) )->user_account();

		return is_wp_error( $account ) ? '' : (string) ( $account['username'] ?? '' );
	}

	/**
	 * POST to the token endpoint with Basic auth and normalize.
	 *
	 * @param array<string, string> $credentials client_id, client_secret.
	 * @param array<string, string> $body        Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $credentials, array $body ) {
		$res = Http::request(
			'POST',
			self::TOKEN_URL,
			array(
				'headers' => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
					'Authorization' => 'Basic ' . base64_encode( $credentials['client_id'] . ':' . $credentials['client_secret'] ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] || empty( $res['json']['access_token'] ) ) {
			return new WP_Error( 'forwp_social_pinterest_token', Http::error_message( $res, __( 'Pinterest token request failed.', '4wp-social' ) ) );
		}

		$json = $res['json'];

		return array(
			'access_token'       => (string) $json['access_token'],
			'refresh_token'      => (string) ( $json['refresh_token'] ?? '' ),
			'expires_at'         => time() + (int) ( $json['expires_in'] ?? 2592000 ),
			'refresh_expires_at' => isset( $json['refresh_token_expires_in'] ) ? time() + (int) $json['refresh_token_expires_in'] : 0,
			'scope'              => (string) ( $json['scope'] ?? '' ),
		);
	}
}
