<?php
/**
 * Meta: Facebook Login for Business (configuration ID) for Facebook Page and Instagram Reels.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

use ForWP\Social\Api\Http;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One connection for facebook.com and instagram.com (ADR 0004).
 *
 * Meta issues no refresh token: the long-lived (or system-user) token is stored as refresh_token
 * and refresh() exchanges it again via fb_exchange_token.
 */
final class Meta_OAuth_Provider implements OAuth_Provider_Interface {

	public const SLUG = 'facebook.com';

	public const GRAPH_VERSION = 'v23.0';

	private const GRAPH_URL = 'https://graph.facebook.com/';

	/**
	 * Lifetime used when Meta returns no expires_in (system-user tokens never expire).
	 */
	private const DEFAULT_TTL = 60 * DAY_IN_SECONDS;

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'Meta (Facebook · Instagram)';
	}

	public function get_credential_labels(): array {
		return array(
			'client_id'     => __( 'App ID', '4wp-social' ),
			'client_secret' => __( 'App secret', '4wp-social' ),
			'config_id'     => __( 'Configuration ID', '4wp-social' ),
			'console_url'   => 'https://developers.facebook.com/apps/',
		);
	}

	public function get_setup_guide(): array {
		return array(
			'console_url'           => 'https://developers.facebook.com/apps/',
			'console_label'         => __( 'Open Meta for Developers', '4wp-social' ),
			'account_hint'          => __( 'Sign in with the Facebook account that manages the Page and the Instagram account.', '4wp-social' ),
			'steps'                 => array(
				__( '<strong>App ID</strong>: Meta for Developers → <strong>My Apps</strong> → your app → left menu <strong>App settings → Basic</strong> → field <strong>App ID</strong> (the same number is in the dashboard URL: <code>/apps/&lt;App ID&gt;/</code>).', '4wp-social' ),
				__( '<strong>App secret</strong>: the same page <strong>App settings → Basic</strong> → field <strong>App secret</strong> → <strong>Show</strong> (Meta asks for your Facebook password) → copy.', '4wp-social' ),
				__( '<strong>Configuration ID</strong>: left menu <strong>Use cases</strong> → <strong>Manage messaging &amp; content on Instagram</strong> → <strong>Customize</strong> → <strong>API setup with Unified login</strong> → block <strong>1. Configure your app</strong> → line <strong>Configuration ID</strong> (copy icon next to the number). The same list is in <strong>Facebook Login for Business → Configurations</strong>.', '4wp-social' ),
				__( 'In that configuration (<strong>Edit permissions</strong>) the permissions must include <code>instagram_basic</code>, <code>instagram_content_publish</code> and, for Facebook Reels, <code>pages_show_list</code>, <code>pages_manage_posts</code>, <code>pages_read_engagement</code> (use case <strong>Manage everything on your Page</strong>).', '4wp-social' ),
				__( '<strong>Redirect URL</strong>: on the same <strong>API setup with Unified login</strong> page → <strong>Register a new redirect URL</strong> → paste the Redirect URI above exactly → <strong>Save</strong>.', '4wp-social' ),
				__( 'Paste App ID, App secret and Configuration ID into step 2 → <strong>Save keys</strong> → <strong>Connect</strong> in step 3.', '4wp-social' ),
				__( 'While the app is <strong>Unpublished</strong>, only people with an app role can connect: <strong>App roles → Roles</strong> → Instagram Tester; the invite is accepted in Instagram → <strong>Settings → Apps and websites → Tester invites</strong>.', '4wp-social' ),
			),
			'notes'                 => array(
				__( 'The Instagram account must be Business or Creator and linked to the Facebook Page.', '4wp-social' ),
			),
			'public_https_required' => false,
			'loopback_allowed'      => false,
			'query_allowed'         => false,
		);
	}

	public function get_auth_url( string $client_id, string $redirect_uri, string $state ): string {
		$params = array(
			'client_id'                      => $client_id,
			'redirect_uri'                   => $redirect_uri,
			'state'                          => $state,
			'response_type'                  => 'code',
			'config_id'                      => Credentials_Store::extra( self::SLUG, 'config_id' ),
			'override_default_response_type' => 'true',
		);

		return 'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	public function exchange_code( array $credentials, string $code, string $redirect_uri ) {
		$short = $this->token_request(
			array(
				'client_id'     => $credentials['client_id'],
				'client_secret' => $credentials['client_secret'],
				'redirect_uri'  => $redirect_uri,
				'code'          => $code,
			)
		);
		if ( is_wp_error( $short ) ) {
			return $short;
		}

		// A system-user token never expires; a user token is swapped for a long-lived one.
		if ( empty( $short['expires_in'] ) ) {
			return $this->normalize( $short );
		}

		$long = $this->refresh( $credentials, (string) $short['access_token'] );

		return is_wp_error( $long ) ? $this->normalize( $short ) : $long;
	}

	public function refresh( array $credentials, string $refresh_token ) {
		$res = $this->token_request(
			array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => $credentials['client_id'],
				'client_secret'     => $credentials['client_secret'],
				'fb_exchange_token' => $refresh_token,
			)
		);

		return is_wp_error( $res ) ? $res : $this->normalize( $res );
	}

	public function get_account_label( string $access_token ): string {
		$assets = self::assets( $access_token );
		$parts  = array();
		foreach ( $assets as $page ) {
			$label = $page['name'];
			if ( '' !== $page['ig_username'] ) {
				$label .= ' · @' . $page['ig_username'];
			}
			$parts[] = $label;
		}
		if ( $parts ) {
			return implode( ', ', $parts );
		}

		$me = self::graph( 'GET', 'me', array( 'fields' => 'name' ), $access_token );

		return is_wp_error( $me ) ? '' : (string) ( $me['name'] ?? '' );
	}

	/**
	 * Pages the token manages, with their linked Instagram account.
	 *
	 * @param string $access_token User or system-user token.
	 * @return array<int, array{id: string, name: string, access_token: string, ig_id: string, ig_username: string}>
	 */
	public static function assets( string $access_token ): array {
		$res = self::graph(
			'GET',
			'me/accounts',
			array( 'fields' => 'id,name,access_token,instagram_business_account{id,username}' ),
			$access_token
		);
		if ( is_wp_error( $res ) ) {
			return array();
		}

		$pages = array();
		foreach ( (array) ( $res['data'] ?? array() ) as $row ) {
			$ig      = (array) ( $row['instagram_business_account'] ?? array() );
			$pages[] = array(
				'id'           => (string) ( $row['id'] ?? '' ),
				'name'         => (string) ( $row['name'] ?? '' ),
				'access_token' => (string) ( $row['access_token'] ?? '' ),
				'ig_id'        => (string) ( $ig['id'] ?? '' ),
				'ig_username'  => (string) ( $ig['username'] ?? '' ),
			);
		}

		return $pages;
	}

	/**
	 * Graph API call.
	 *
	 * @param string               $method       GET|POST.
	 * @param string               $path         Path after the version (me/accounts).
	 * @param array<string, mixed> $params       Query (GET) or form body (POST).
	 * @param string               $access_token Token.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function graph( string $method, string $path, array $params, string $access_token ) {
		$url  = self::GRAPH_URL . self::GRAPH_VERSION . '/' . ltrim( $path, '/' );
		$args = array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) );
		if ( 'GET' === $method ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), $url );
		} else {
			$args['body'] = $params;
		}

		$res = Http::request( $method, $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			return new WP_Error( 'forwp_social_meta_graph', self::error_message( $res ) );
		}

		return (array) $res['json'];
	}

	/**
	 * GET the token endpoint.
	 *
	 * @param array<string, string> $params Query.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $params ) {
		$url = add_query_arg( array_map( 'rawurlencode', $params ), self::GRAPH_URL . self::GRAPH_VERSION . '/oauth/access_token' );
		$res = Http::request( 'GET', $url, array() );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $res['code'] || empty( $res['json']['access_token'] ) ) {
			return new WP_Error( 'forwp_social_meta_token', self::error_message( $res ) );
		}

		return (array) $res['json'];
	}

	/**
	 * Token response → stored shape (token doubles as refresh_token).
	 *
	 * @param array<string, mixed> $json Token response.
	 * @return array<string, mixed>
	 */
	private function normalize( array $json ): array {
		$ttl = (int) ( $json['expires_in'] ?? 0 );

		return array(
			'access_token'  => (string) $json['access_token'],
			'refresh_token' => (string) $json['access_token'],
			'expires_at'    => time() + ( $ttl > 0 ? $ttl : self::DEFAULT_TTL ),
			'token_type'    => $ttl > 0 ? 'user' : 'system_user',
		);
	}

	/**
	 * Graph error text.
	 *
	 * @param array{code: int, body: string, json: array<string, mixed>|null} $res Http result.
	 */
	private static function error_message( array $res ): string {
		return Http::error_message( $res, __( 'Meta request failed.', '4wp-social' ) );
	}
}
