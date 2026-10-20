<?php
/**
 * Explain why a redirect URI will be rejected before the admin tries to connect.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the site's OAuth callback against platform rules.
 */
final class Redirect_Check {

	/**
	 * Problems with a redirect URI for one platform.
	 *
	 * @param string               $uri   Redirect URI.
	 * @param array<string, mixed> $guide Provider setup guide.
	 * @return array<int, array{level: string, text: string}> level: error|warn.
	 */
	public static function issues( string $uri, array $guide ): array {
		$issues = array();
		$scheme = (string) wp_parse_url( $uri, PHP_URL_SCHEME );
		$host   = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );

		if ( empty( $guide['query_allowed'] ) && false !== strpos( $uri, '?' ) ) {
			$issues[] = array(
				'level' => 'error',
				'text'  => __( 'This platform rejects redirect URIs with a query string. Turn on pretty permalinks in Settings → Permalinks (any option except Plain).', '4wp-social' ),
			);
		}

		$loopback = in_array( $host, array( '127.0.0.1', 'localhost' ), true );
		if ( $loopback && ! empty( $guide['loopback_allowed'] ) ) {
			return $issues;
		}

		if ( ! empty( $guide['loopback_allowed'] ) && self::is_private_host( $host ) ) {
			$issues[] = array(
				'level' => 'warn',
				'text'  => sprintf(
					/* translators: %s: host name. */
					__( '%s is a local domain and the platform rejects it. Press "Use loopback redirect" in the Development mode box above, then use the 127.0.0.1 address in the console.', '4wp-social' ),
					$host
				),
			);
		} elseif ( ! empty( $guide['public_https_required'] ) && self::is_private_host( $host ) ) {
			$issues[] = array(
				'level' => 'error',
				'text'  => sprintf(
					/* translators: %s: host name. */
					__( '%s is not a public domain, so the platform cannot send you back here. Connect on a public URL: a tunnel to this site (Local Live Link, ngrok, Cloudflare Tunnel) or a staging domain, and use that address in the console.', '4wp-social' ),
					$host
				),
			);
		} elseif ( 'https' !== $scheme ) {
			$issues[] = array(
				'level' => 'error',
				'text'  => __( 'The redirect URI must start with https://. Enable SSL for the site.', '4wp-social' ),
			);
		}

		return $issues;
	}

	/**
	 * Local or reserved host names the platforms will not redirect to.
	 *
	 * @param string $host Host.
	 */
	public static function is_private_host( string $host ): bool {
		if ( '' === $host || false === strpos( $host, '.' ) ) {
			return true;
		}
		if ( preg_match( '/\.(local|test|localhost|invalid|example|internal|lan|home)$/', $host ) ) {
			return true;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return false;
	}
}
