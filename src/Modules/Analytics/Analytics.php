<?php
/**
 * Analytics: source registry and raw snapshots.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics;

use ForWP\Social\Contracts\Analytics_Import_Interface;
use ForWP\Social\Contracts\Analytics_Source_Interface;
use ForWP\Social\Modules\Analytics\Sources\Facebook_Source;
use ForWP\Social\Modules\Analytics\Sources\Instagram_Source;
use ForWP\Social\Modules\Analytics\Sources\LinkedIn_Source;
use ForWP\Social\Modules\Analytics\Sources\Planned_Source;
use ForWP\Social\Modules\Analytics\Sources\TikTok_Source;
use ForWP\Social\Modules\Analytics\Sources\YouTube_Source;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sources, fetch on demand, last snapshot per source.
 */
final class Analytics {

	public const OPTION = 'forwp_social_analytics_snapshots';

	public const READY         = 'ready';
	public const NOT_CONNECTED = 'not_connected';
	public const PLANNED       = 'planned';
	public const MANUAL        = 'manual';

	/**
	 * Snapshot part that holds the last imported file.
	 */
	public const IMPORT_KEY = 'import';

	/**
	 * Sources keyed by slug.
	 *
	 * @return array<string, Analytics_Source_Interface>
	 */
	public static function sources(): array {
		$sources = array(
			new YouTube_Source(),
			new TikTok_Source(),
			new Facebook_Source(),
			new Instagram_Source(),
			new Planned_Source( 'analytics.google.com', __( 'Google Analytics', '4wp-social' ), __( 'GA4 Data API: site traffic next to social reach. Planned.', '4wp-social' ) ),
			new LinkedIn_Source(),
		);

		/**
		 * Analytics sources.
		 *
		 * @param Analytics_Source_Interface[] $sources Sources.
		 */
		$sources = (array) apply_filters( 'forwp_social_analytics_sources', $sources );

		$out = array();
		foreach ( $sources as $source ) {
			if ( $source instanceof Analytics_Source_Interface ) {
				$out[ $source->slug() ] = $source;
			}
		}

		return $out;
	}

	/**
	 * One source.
	 *
	 * @param string $slug Slug.
	 */
	public static function source( string $slug ): ?Analytics_Source_Interface {
		return self::sources()[ $slug ] ?? null;
	}

	/**
	 * All snapshots: slug => { fetched_at, data }.
	 *
	 * @return array<string, array{fetched_at: int, data: array<string, mixed>}>
	 */
	public static function snapshots(): array {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * Snapshot of one source.
	 *
	 * @param string $slug Slug.
	 * @return array{fetched_at: int, data: array<string, mixed>}|null
	 */
	public static function snapshot( string $slug ): ?array {
		return self::snapshots()[ $slug ] ?? null;
	}

	/**
	 * Store a snapshot.
	 *
	 * @param string               $slug Slug.
	 * @param array<string, mixed> $data Raw data.
	 * @param int                  $time Fetch time.
	 */
	public static function save( string $slug, array $data, int $time ): void {
		$all          = self::snapshots();
		$all[ $slug ] = array(
			'fetched_at' => $time,
			'data'       => $data,
		);
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Fetch a source now and store the result.
	 *
	 * @param string $slug Slug.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function refresh( string $slug ) {
		$source = self::source( $slug );
		if ( ! $source ) {
			return new WP_Error( 'forwp_social_analytics_source', __( 'Unknown analytics source.', '4wp-social' ) );
		}
		if ( self::READY !== $source->status() ) {
			return new WP_Error( 'forwp_social_analytics_not_ready', __( 'This source is not connected.', '4wp-social' ) );
		}

		$data = $source->fetch();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// An earlier manual import stays next to the API data.
		$previous = self::snapshot( $slug );
		if ( isset( $previous['data'][ self::IMPORT_KEY ] ) ) {
			$data[ self::IMPORT_KEY ] = $previous['data'][ self::IMPORT_KEY ];
		}

		self::save( $slug, $data, time() );

		return $data;
	}

	/**
	 * Import an uploaded file into a source and store the result.
	 *
	 * @param string $slug Slug.
	 * @param string $path Temporary file path.
	 * @param string $name Original file name.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function import( string $slug, string $path, string $name ) {
		$source = self::source( $slug );
		if ( ! $source instanceof Analytics_Import_Interface ) {
			return new WP_Error( 'forwp_social_analytics_source', __( 'This source does not take files.', '4wp-social' ) );
		}

		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $source->extensions(), true ) ) {
			/* translators: %s: allowed extensions, e.g. csv. */
			return new WP_Error( 'forwp_social_analytics_file', sprintf( __( 'Upload a file of type: %s.', '4wp-social' ), implode( ', ', $source->extensions() ) ) );
		}

		$import = $source->import( $path, $name );
		if ( is_wp_error( $import ) ) {
			return $import;
		}

		// The file goes under `import`; API parts of the snapshot stay.
		$previous                 = self::snapshot( $slug );
		$data                     = is_array( $previous['data'] ?? null ) ? $previous['data'] : array();
		$data[ self::IMPORT_KEY ] = $import;

		self::save( $slug, $data, time() );

		return $data;
	}

	/**
	 * API answer or `{ "error": "..." }`, so one failed call does not hide the others.
	 *
	 * @param mixed $result Result of a call.
	 * @return array<string, mixed>
	 */
	public static function part( $result ): array {
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return is_array( $result ) ? $result : array( 'value' => $result );
	}

	/**
	 * Date range: last N days, ending yesterday (Y-m-d).
	 *
	 * @param int $days Days.
	 * @param int $now  Timestamp.
	 * @return array{0: string, 1: string}
	 */
	public static function range( int $days, int $now ): array {
		$end   = $now - DAY_IN_SECONDS;
		$start = $end - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS;

		return array( gmdate( 'Y-m-d', $start ), gmdate( 'Y-m-d', $end ) );
	}
}
