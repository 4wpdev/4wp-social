<?php
/**
 * Encrypted OAuth tokens of the connected brand account, one option per platform.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Token storage for one platform.
 */
final class Token_Store {

	private const OPTION_PREFIX = 'forwp_social_tokens_';

	/**
	 * @var string
	 */
	private $platform;

	/**
	 * @param string $platform Platform key (youtube.com).
	 */
	public function __construct( string $platform ) {
		$this->platform = $platform;
	}

	/**
	 * Option name: forwp_social_tokens_youtube_com.
	 */
	public function option_name(): string {
		return self::OPTION_PREFIX . str_replace( array( '.', '-' ), '_', sanitize_key( $this->platform ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(): array {
		return Crypto::read_option( $this->option_name() );
	}

	/**
	 * @param array<string, mixed> $tokens Normalized tokens.
	 */
	public function save( array $tokens ): void {
		Crypto::save_option( $this->option_name(), $tokens );
	}

	/**
	 * @return void
	 */
	public function delete(): void {
		delete_option( $this->option_name() );
	}

	/**
	 * Whether a refresh token is stored.
	 */
	public function is_connected(): bool {
		return '' !== (string) ( $this->get()['refresh_token'] ?? '' );
	}
}
