<?php
/**
 * What one platform receives for one post.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Value object built by Package_Reader from meta.json + package files.
 */
final class Media_Package {

	/**
	 * @var array<string, mixed>
	 */
	private $data;

	/**
	 * @param array<string, mixed> $data Fields (see defaults()).
	 */
	public function __construct( array $data ) {
		$this->data = array_merge( self::defaults(), array_intersect_key( $data, self::defaults() ) );
	}

	/**
	 * Field defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'post_id'           => 0,
			'platform'          => '',
			'entry'             => array(),
			'title'             => '',
			'description'       => '',
			'tags'              => array(),
			'privacy'           => 'public',
			'video_path'        => '',
			'captions_path'     => '',
			'captions_language' => 'en',
			'cover_path'        => '',
			'cover_ms'          => 0,
			'temp_files'        => array(),
		);
	}

	/**
	 * Copy with changed fields (used by forwp_social_media_package filter callbacks).
	 *
	 * @param array<string, mixed> $changes Fields to change.
	 */
	public function with( array $changes ): self {
		return new self( array_merge( $this->data, $changes ) );
	}

	/**
	 * All fields.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	public function post_id(): int {
		return (int) $this->data['post_id'];
	}

	public function platform(): string {
		return (string) $this->data['platform'];
	}

	/**
	 * Raw meta.json platform entry.
	 *
	 * @return array<string, mixed>
	 */
	public function entry(): array {
		return is_array( $this->data['entry'] ) ? $this->data['entry'] : array();
	}

	public function title(): string {
		return (string) $this->data['title'];
	}

	public function description(): string {
		return (string) $this->data['description'];
	}

	/**
	 * @return array<int, string>
	 */
	public function tags(): array {
		return array_values( array_filter( array_map( 'strval', (array) $this->data['tags'] ) ) );
	}

	/**
	 * public|unlisted|private.
	 */
	public function privacy(): string {
		return (string) $this->data['privacy'];
	}

	public function video_path(): string {
		return (string) $this->data['video_path'];
	}

	public function captions_path(): string {
		return (string) $this->data['captions_path'];
	}

	public function captions_language(): string {
		return (string) $this->data['captions_language'];
	}

	public function cover_path(): string {
		return (string) $this->data['cover_path'];
	}

	public function cover_ms(): int {
		return max( 0, (int) $this->data['cover_ms'] );
	}

	/**
	 * Whether a readable video file is attached.
	 */
	public function has_video(): bool {
		return '' !== $this->video_path() && is_readable( $this->video_path() );
	}

	/**
	 * Delete temp files downloaded from the package.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		foreach ( (array) $this->data['temp_files'] as $file ) {
			if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Caption language from a file name: video.en.srt → en.
	 *
	 * @param string $file     File name.
	 * @param string $fallback Default language.
	 */
	public static function language_from_file( string $file, string $fallback = 'en' ): string {
		if ( preg_match( '/\.([a-z]{2,3}(?:[-_][A-Za-z]{2,4})?)\.(?:srt|vtt)$/i', basename( $file ), $m ) ) {
			return str_replace( '_', '-', $m[1] );
		}

		return $fallback;
	}
}
