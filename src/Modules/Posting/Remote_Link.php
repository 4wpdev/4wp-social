<?php
/**
 * Link a post to a copy that already exists on a platform (published by hand), without uploading again.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Core\Publish_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Three ways in: meta.json ("status": "posted" + "id"), search by title, ID typed by hand.
 */
final class Remote_Link {

	/**
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'forwp_social_package_imported', array( self::class, 'from_package' ), 10, 2 );
	}

	/**
	 * meta.json entries marked as posted become links on the post.
	 *
	 * @param int                  $post_id  Post id.
	 * @param array<string, mixed> $snapshot Platforms snapshot.
	 * @return void
	 */
	public static function from_package( $post_id, $snapshot ): void {
		$post_id = (int) $post_id;
		$done    = ( new Posting_Service() )->published_map( $post_id );
		foreach ( (array) $snapshot as $platform => $item ) {
			$entry = is_array( $item['entry'] ?? null ) ? $item['entry'] : array();
			$id    = trim( (string) ( $entry['id'] ?? '' ) );
			if ( 'posted' !== ( $entry['status'] ?? '' ) || '' === $id || isset( $done[ $platform ] ) ) {
				continue;
			}
			self::link( $post_id, (string) $platform, $id, (string) ( $entry['url'] ?? '' ), 'meta.json' );
		}
	}

	/**
	 * Remember the remote copy: same meta and hook as a real publication.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 * @param string $id       Remote id.
	 * @param string $url      Remote URL (built for YouTube when empty).
	 * @param string $via      meta.json|search|manual.
	 * @return array{id: string, url: string, at: string, via: string}
	 */
	public static function link( int $post_id, string $platform, string $id, string $url = '', string $via = 'manual' ): array {
		if ( '' === $url && 'youtube.com' === $platform ) {
			$url = 'https://www.youtube.com/shorts/' . rawurlencode( $id );
		}

		$row     = array(
			'id'  => $id,
			'url' => $url,
			'at'  => gmdate( 'c' ),
			'via' => $via,
		);
		$service = new Posting_Service();
		$map     = $service->published_map( $post_id );

		$map[ $platform ] = $row;
		update_post_meta( $post_id, Posting_Service::PUBLISHED_META, $map );

		/** This action is documented in src/Modules/Posting/Posting_Service.php */
		do_action( 'forwp_social_published', $post_id, $platform, Publish_Result::published( $id, $url ) );

		return $row;
	}

	/**
	 * Forget the link (the button can publish again).
	 *
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 * @return void
	 */
	public static function unlink( int $post_id, string $platform ): void {
		$map = ( new Posting_Service() )->published_map( $post_id );
		unset( $map[ $platform ] );
		update_post_meta( $post_id, Posting_Service::PUBLISHED_META, $map );

		$urls = get_post_meta( $post_id, 'platform_urls', true );
		if ( is_array( $urls ) && isset( $urls[ $platform ] ) ) {
			unset( $urls[ $platform ] );
			update_post_meta( $post_id, 'platform_urls', $urls );
		}
		if ( 'youtube.com' === $platform ) {
			delete_post_meta( $post_id, 'youtube_id' );
		}
	}

	/**
	 * YouTube video id from an id, a watch / shorts / youtu.be / embed URL.
	 *
	 * @param string $input Id or URL.
	 */
	public static function youtube_id( string $input ): string {
		$input = trim( $input );
		if ( preg_match( '~(?:v=|/shorts/|youtu\.be/|/embed/|/live/)([A-Za-z0-9_-]{11})~', $input, $m ) ) {
			return $m[1];
		}

		return preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ? $input : '';
	}

	/**
	 * Rank videos by how many title words they share with the query (best first).
	 *
	 * @param string                                  $query  Post title.
	 * @param array<int, array{title: string}>        $videos Videos.
	 * @param int                                     $limit  Max results.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rank( string $query, array $videos, int $limit = 8 ): array {
		$words = self::words( $query );
		if ( ! $words ) {
			return array();
		}

		$scored = array();
		foreach ( $videos as $video ) {
			$score = count( array_intersect( $words, self::words( (string) $video['title'] ) ) ) / count( $words );
			if ( $score > 0 ) {
				$video['score'] = round( $score, 2 );
				$scored[]       = $video;
			}
		}
		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, $limit );
	}

	/**
	 * Lowercase words of 3+ characters, without hashtags.
	 *
	 * @param string $text Text.
	 * @return array<int, string>
	 */
	private static function words( string $text ): array {
		$text = strtolower( (string) preg_replace( '/#\S+/', ' ', $text ) );
		preg_match_all( '/[a-z0-9]{3,}/', $text, $m );

		return array_values( array_unique( $m[0] ) );
	}
}
