<?php
/**
 * Posting contour: the site publishes to platforms (API or manual).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Contracts\Module_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * MVP: YouTube and TikTok video cross-posting through the platform APIs.
 */
final class Posting_Module implements Module_Interface {

	public const SLUG = 'posting';

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return __( 'Posting', '4wp-social' );
	}

	public function get_description(): string {
		return __( 'Publish your posts to social platforms: video cross-posting to YouTube and TikTok from a meta.json package.', '4wp-social' );
	}

	public function is_implemented(): bool {
		return true;
	}

	public function boot(): void {
		Queue::boot();
		Drive_Bridge::boot();
		Post_Metabox::boot();
		Remote_Link::boot();
		Abilities::boot();
		add_filter( 'upload_mimes', array( self::class, 'caption_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( self::class, 'caption_filetype' ), 10, 3 );
	}

	/**
	 * Allow SRT / VTT caption files in the Media Library.
	 *
	 * @param array<string, string> $mimes Allowed mimes.
	 * @return array<string, string>
	 */
	public static function caption_mimes( array $mimes ): array {
		$mimes['srt'] = 'application/x-subrip';
		$mimes['vtt'] = 'text/vtt';

		return $mimes;
	}

	/**
	 * SRT / VTT are detected as text/plain; accept them by extension.
	 *
	 * @param array<string, mixed> $data     Detected type.
	 * @param string               $file     Temp path.
	 * @param string               $filename Original name.
	 * @return array<string, mixed>
	 */
	public static function caption_filetype( $data, $file, $filename ): array {
		$data = is_array( $data ) ? $data : array();
		if ( ! empty( $data['ext'] ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( 'srt' === $ext || 'vtt' === $ext ) {
			$data['ext']  = $ext;
			$data['type'] = 'srt' === $ext ? 'application/x-subrip' : 'text/vtt';
		}

		return $data;
	}
}
