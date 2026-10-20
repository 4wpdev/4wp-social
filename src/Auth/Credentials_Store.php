<?php
/**
 * OAuth app credentials per platform (Client ID / key + secret), encrypted.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * One encrypted option for all platforms.
 */
final class Credentials_Store {

	public const OPTION_KEY = 'forwp_social_credentials';

	/**
	 * @param string $platform Platform key.
	 * @return array{client_id: string, client_secret: string}
	 */
	public static function get( string $platform ): array {
		$all  = Crypto::read_option( self::OPTION_KEY );
		$item = isset( $all[ $platform ] ) && is_array( $all[ $platform ] ) ? $all[ $platform ] : array();

		return array(
			'client_id'     => (string) ( $item['client_id'] ?? '' ),
			'client_secret' => (string) ( $item['client_secret'] ?? '' ),
		);
	}

	/**
	 * @param string $platform Platform key.
	 */
	public static function has( string $platform ): bool {
		$creds = self::get( $platform );

		return '' !== $creds['client_id'] && '' !== $creds['client_secret'];
	}

	/**
	 * Save credentials. An empty secret keeps the stored one (the form never shows it).
	 *
	 * @param string $platform      Platform key.
	 * @param string $client_id     Client ID / key.
	 * @param string $client_secret Client secret.
	 */
	public static function save( string $platform, string $client_id, string $client_secret ): void {
		$all     = Crypto::read_option( self::OPTION_KEY );
		$current = self::get( $platform );

		$all[ $platform ] = array(
			'client_id'     => $client_id,
			'client_secret' => '' !== $client_secret ? $client_secret : $current['client_secret'],
		);

		Crypto::save_option( self::OPTION_KEY, $all );
	}
}
