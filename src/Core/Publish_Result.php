<?php
/**
 * Result of one publish or status-check call.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object returned by platform adapters.
 */
final class Publish_Result {

	public const PUBLISHED  = 'published';
	public const PROCESSING = 'processing';
	public const FAILED     = 'failed';

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $remote_id;

	/**
	 * @var string
	 */
	private $remote_url;

	/**
	 * @var string
	 */
	private $reference;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @var bool
	 */
	private $retryable;

	/**
	 * @param string $status     One of the constants.
	 * @param string $remote_id  Platform post / video id.
	 * @param string $remote_url Public URL on the platform.
	 * @param string $reference  Platform job reference for polling.
	 * @param string $message    Warning or error text.
	 * @param bool   $retryable  Whether a failed call may be retried.
	 */
	private function __construct( string $status, string $remote_id, string $remote_url, string $reference, string $message, bool $retryable ) {
		$this->status     = $status;
		$this->remote_id  = $remote_id;
		$this->remote_url = $remote_url;
		$this->reference  = $reference;
		$this->message    = $message;
		$this->retryable  = $retryable;
	}

	/**
	 * Published, with an optional warning (e.g. captions failed).
	 *
	 * @param string $remote_id  Platform id.
	 * @param string $remote_url Public URL.
	 * @param string $warning    Warning text.
	 */
	public static function published( string $remote_id, string $remote_url, string $warning = '' ): self {
		return new self( self::PUBLISHED, $remote_id, $remote_url, '', $warning, false );
	}

	/**
	 * Accepted by the platform, still processing.
	 *
	 * @param string $reference Reference for check().
	 * @param string $message   Status text.
	 */
	public static function processing( string $reference, string $message = '' ): self {
		return new self( self::PROCESSING, '', '', $reference, $message, true );
	}

	/**
	 * Failed.
	 *
	 * @param string $message   Error text.
	 * @param bool   $retryable Whether the queue may retry.
	 */
	public static function failed( string $message, bool $retryable = true ): self {
		return new self( self::FAILED, '', '', '', $message, $retryable );
	}

	public function status(): string {
		return $this->status;
	}

	public function remote_id(): string {
		return $this->remote_id;
	}

	public function remote_url(): string {
		return $this->remote_url;
	}

	public function reference(): string {
		return $this->reference;
	}

	public function message(): string {
		return $this->message;
	}

	public function is_retryable(): bool {
		return $this->retryable;
	}

	public function is_published(): bool {
		return self::PUBLISHED === $this->status;
	}

	public function is_processing(): bool {
		return self::PROCESSING === $this->status;
	}

	public function is_failed(): bool {
		return self::FAILED === $this->status;
	}
}
