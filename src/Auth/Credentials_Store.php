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
	 * Extra non-secret field of a platform (Meta: config_id).
	 *
	 * @param string $platform Platform key.
	 * @param string $field    Field name.
	 */
	public static function extra( string $platform, string $field ): string {
		$all = Crypto::read_option( self::OPTION_KEY );

		return (string) ( $all[ $platform ]['extra'][ $field ] ?? '' );
	}

	/**
	 * Save credentials. An empty secret keeps the stored one (the form never shows it).
	 *
	 * @param string                $platform      Platform key.
	 * @param string                $client_id     Client ID / key.
	 * @param string                $client_secret Client secret.
	 * @param array<string, string> $extra         Extra fields (config_id); null keeps the stored ones.
	 */
	public static function save( string $platform, string $client_id, string $client_secret, ?array $extra = null ): void {
		$all     = Crypto::read_option( self::OPTION_KEY );
		$current = self::get( $platform );
		$stored  = isset( $all[ $platform ]['extra'] ) && is_array( $all[ $platform ]['extra'] ) ? $all[ $platform ]['extra'] : array();

		$all[ $platform ] = array(
			'client_id'     => $client_id,
			'client_secret' => '' !== $client_secret ? $client_secret : $current['client_secret'],
			'extra'         => null !== $extra ? array_map( 'strval', $extra ) : $stored,
		);

		Crypto::save_option( self::OPTION_KEY, $all );
	}
}
