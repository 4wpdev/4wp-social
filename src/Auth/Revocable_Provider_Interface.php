<?php
/**
 * A provider that can revoke a grant on the platform side.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Disconnect calls revoke() before the local tokens are deleted.
 */
interface Revocable_Provider_Interface {

	/**
	 * Revoke the grant behind a token (refresh token revokes the whole grant).
	 *
	 * @param string $token Refresh or access token.
	 * @return true|\WP_Error
	 */
	public function revoke( string $token );
}
