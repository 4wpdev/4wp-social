<?php
/**
 * Google OAuth for the YouTube channel of the brand.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\Http;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * youtube.upload + youtube.force-ssl (captions, thumbnails, channel name).
 */
final class Google_OAuth_Provider implements OAuth_Provider_Interface, Revocable_Provider_Interface {

	public const SLUG = 'youtube.com';

	private const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
	private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

	private const SCOPES = array(
		'https://www.googleapis.com/auth/youtube.upload',
		'https://www.googleapis.com/auth/youtube.force-ssl',
	);

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'YouTube';
	}

	public function get_credential_labels(): array {
		return array(
			'client_id'     => __( 'Client ID', '4wp-social' ),
			'client_secret' => __( 'Client secret', '4wp-social' ),
			'console_url'   => 'https://console.cloud.google.com/apis/credentials',
		);
	}

	public function get_setup_guide(): array {
		return array(
			'console_url'           => 'https://console.cloud.google.com/apis/library/youtube.googleapis.com',
			'console_label'         => __( 'Open Google Cloud Console', '4wp-social' ),
			'account_hint'          => __( 'Sign in with the Google account that owns the YouTube channel.', '4wp-social' ),
			'steps'                 => array(
				__( '<strong>Check the project.</strong> In the top bar of Google Cloud Console the project selector must show your project (the one where you enabled the API).', '4wp-social' ),
				__( '<strong>Turn on the API.</strong> Open <a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener noreferrer">YouTube Data API v3</a> → press <strong>Enable</strong>. If you see <strong>API Enabled</strong> and a <strong>Manage</strong> button, it is already done.', '4wp-social' ),
				__( '<strong>Describe the app.</strong> Open <a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener noreferrer">Google Auth Platform</a> → <strong>Get started</strong>. App name: your site name. User support email: your email → Next. Audience: <strong>External</strong> → Next. Contact information: your email → Next. Agree → <strong>Create</strong>. (Already configured? Skip this step.)', '4wp-social' ),
				__( '<strong>Add yourself as a tester.</strong> Open <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noopener noreferrer">Audience</a> → <strong>Test users</strong> → <strong>Add users</strong> → the Google account that owns the YouTube channel → <strong>Save</strong>.', '4wp-social' ),
				__( '<strong>Allow uploads.</strong> Open <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noopener noreferrer">Data Access</a> → <strong>Add or remove scopes</strong> → in the filter type <code>youtube.upload</code> and tick it, then <code>youtube.force-ssl</code> and tick it → <strong>Update</strong> → <strong>Save</strong> at the bottom of the page.', '4wp-social' ),
				__( '<strong>Local site only:</strong> in the <strong>Development mode</strong> box above press <strong>Use loopback redirect</strong>. The Redirect URI at the top changes to <code>http://127.0.0.1:…</code>.', '4wp-social' ),
				__( '<strong>Create the keys.</strong> Open <a href="https://console.cloud.google.com/auth/clients/create" target="_blank" rel="noopener noreferrer">Create OAuth client</a> → Application type: <strong>Web application</strong> → Name: anything (e.g. 4WP Social) → <strong>Authorized redirect URIs</strong> → <strong>Add URI</strong> → paste the Redirect URI from the top of this box (press Copy) → <strong>Create</strong>.', '4wp-social' ),
				__( '<strong>Copy the keys.</strong> The dialog shows <strong>Client ID</strong> and <strong>Client secret</strong>. Paste both into step 2 below right away — Google shows the secret only once (or press Download JSON to keep a copy).', '4wp-social' ),
			),
			'notes'                 => array(
				__( 'While the app is in <strong>Testing</strong>, Google expires the connection after 7 days: press Reconnect weekly, or publish the app in <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noopener noreferrer">Audience</a> → Publish app.', '4wp-social' ),
				__( 'Until Google audits the app, videos uploaded through the API stay <strong>private</strong>.', '4wp-social' ),
				__( 'Custom thumbnails work only on a verified channel (<a href="https://www.youtube.com/verify" target="_blank" rel="noopener noreferrer">youtube.com/verify</a>).', '4wp-social' ),
			),
			'public_https_required' => true,
			'loopback_allowed'      => true,
			'query_allowed'         => true,
		);
	}

	public function get_auth_url( string $client_id, string $redirect_uri, string $state ): string {
		$params = array(
			'client_id'              => $client_id,
			'redirect_uri'           => $redirect_uri,
			'response_type'          => 'code',
			'scope'                  => implode( ' ', self::SCOPES ),
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	public function exchange_code( array $credentials, string $code, string $redirect_uri ) {
		return $this->token_request(
			array(
				'code'          => $code,
				'client_id'     => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			)
		);
	}

	public function refresh( array $credentials, string $refresh_token ) {
		return $this->token_request(
			array(
				'refresh_token' => $refresh_token,
				'client_id'     => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'grant_type'    => 'refresh_token',
			)
		);
	}

	public function get_account_label( string $access_token ): string {
		$res = Http::request(
			'GET',
			'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true',
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) )
		);
		if ( is_wp_error( $res ) || 200 !== $res['code'] ) {
			return '';
		}

		return (string) ( $res['json']['items'][0]['snippet']['title'] ?? '' );
	}

	/**
	 * POST to the token endpoint and normalize.
	 *
	 * @param array<string, string> $body Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ) {
		$res = Http::request( 'POST', self::TOKEN_URL, array( 'body' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] || empty( $res['json']['access_token'] ) ) {
			return new WP_Error( 'forwp_social_google_token', Http::error_message( $res, __( 'Google token request failed.', '4wp-social' ) ) );
		}

		$json   = $res['json'];
		$tokens = array(
			'access_token' => (string) $json['access_token'],
			'expires_at'   => time() + (int) ( $json['expires_in'] ?? 3600 ),
			'scope'        => (string) ( $json['scope'] ?? '' ),
		);
		if ( ! empty( $json['refresh_token'] ) ) {
			$tokens['refresh_token'] = (string) $json['refresh_token'];
		}

		return $tokens;
	}

	/**
	 * Revoke the grant at Google, so Disconnect also removes 4wp.dev from the account's permissions.
	 *
	 * @param string $token Refresh or access token.
	 * @return true|WP_Error
	 */
	public function revoke( string $token ) {
		$res = Http::request( 'POST', self::REVOKE_URL, array( 'body' => array( 'token' => $token ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		// 400 invalid_token: already revoked on Google's side, nothing left to do.
		if ( 200 !== $res['code'] && 400 !== $res['code'] ) {
			return new WP_Error( 'forwp_social_google_revoke', Http::error_message( $res, __( 'Google did not confirm the revocation.', '4wp-social' ) ) );
		}

		return true;
	}
}
