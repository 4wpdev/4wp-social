<?php
/**
 * Local index "post × platform": the post list and the calendar read only this (ADR 0002).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Social\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta: {platform: {mode, status, url, remote_id, run_at, updated_at, via}}.
 */
final class Status_Index {

	public const META = '_forwp_social_status';

	public const PLANNED        = 'planned';
	public const MANUAL_PENDING = 'manual_pending';

	public const MODE_API    = 'api';
	public const MODE_MANUAL = 'manual';

	/**
	 * @param int $post_id Post id.
	 * @return array<string, array<string, string>>
	 */
	public static function get( int $post_id ): array {
		$index = get_post_meta( $post_id, self::META, true );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Merge fields into one platform. Empty url / remote_id never erase known ones.
	 *
	 * @param int                   $post_id  Post id.
	 * @param string                $platform Platform key.
	 * @param array<string, string> $fields   Fields.
	 * @return void
	 */
	public static function put( int $post_id, string $platform, array $fields ): void {
		$index              = self::get( $post_id );
		$index[ $platform ] = self::merge_entry( $index[ $platform ] ?? array(), $fields );
		update_post_meta( $post_id, self::META, $index );
	}

	/**
	 * @param int    $post_id  Post id.
	 * @param string $platform Platform key.
	 * @return void
	 */
	public static function forget( int $post_id, string $platform ): void {
		$index = self::get( $post_id );
		unset( $index[ $platform ] );
		update_post_meta( $post_id, self::META, $index );
	}

	/**
	 * @param array<string, string> $current Current entry.
	 * @param array<string, string> $fields  New fields.
	 * @return array<string, string>
	 */
	public static function merge_entry( array $current, array $fields ): array {
		foreach ( array( 'url', 'remote_id' ) as $key ) {
			if ( isset( $fields[ $key ], $current[ $key ] ) && '' === $fields[ $key ] ) {
				unset( $fields[ $key ] );
			}
		}

		return array_merge(
			array(
				'mode'       => self::MODE_API,
				'status'     => self::PLANNED,
				'url'        => '',
				'remote_id'  => '',
				'run_at'     => '',
				'updated_at' => gmdate( 'c' ),
				'via'        => self::MODE_API,
			),
			$current,
			array( 'updated_at' => gmdate( 'c' ) ),
			$fields
		);
	}

	/**
	 * Fields of a queue job.
	 *
	 * @param object $job Job row.
	 * @return array<string, string>
	 */
	public static function fields_from_job( object $job ): array {
		$run_at = (string) ( $job->run_at ?? '' );

		return array(
			'mode'      => self::MODE_API,
			'status'    => (string) $job->status,
			'url'       => (string) ( $job->remote_url ?? '' ),
			'remote_id' => (string) ( $job->remote_id ?? '' ),
			'run_at'    => '' !== $run_at ? gmdate( 'c', (int) strtotime( $run_at . ' UTC' ) ) : '',
			'via'       => self::MODE_API,
		);
	}

	/**
	 * The queue writes through here.
	 *
	 * @param object $job Job row.
	 * @return void
	 */
	public static function from_job( object $job ): void {
		self::put( (int) $job->post_id, (string) $job->platform, self::fields_from_job( $job ) );
	}

	/**
	 * Merge meta.json into the index. Pure: queue and manual results always win over the package.
	 *
	 * @param array<string, array<string, string>> $current  Current index.
	 * @param array<string, mixed>                 $package  meta.json data.
	 * @param array<string, array<string, mixed>>  $registry Platform_Registry rows by key.
	 * @param array<string, int|bool>              $context  live_at (0 = not scheduled), now, api_delay, manual_delay, auto_publish.
	 * @return array<string, array<string, string>>
	 */
	public static function merge_package( array $current, array $package, array $registry, array $context ): array {
		$declared = isset( $package['platforms'] ) && is_array( $package['platforms'] ) ? $package['platforms'] : array();

		// Plans that left meta.json leave the index; results stay.
		foreach ( $current as $key => $entry ) {
			if ( ! isset( $declared[ $key ] ) && self::is_plan( $entry ) && 'meta.json' === ( $entry['via'] ?? '' ) ) {
				unset( $current[ $key ] );
			}
		}

		foreach ( $declared as $key => $entry ) {
			$key = (string) $key;
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$raw    = (string) ( $entry['status'] ?? '' );
			$known  = $current[ $key ] ?? null;
			$posted = 'posted' === $raw;

			if ( $posted ) {
				if ( ! $known || Job_Repository::PUBLISHED !== ( $known['status'] ?? '' ) ) {
					$current[ $key ] = self::merge_entry(
						$known ?? array(),
						array(
							'mode'       => self::mode( $entry, $registry[ $key ] ?? null ),
							'status'     => Job_Repository::PUBLISHED,
							'url'        => (string) ( $entry['url'] ?? '' ),
							'remote_id'  => (string) ( $entry['id'] ?? ( $entry['slug'] ?? '' ) ),
							'updated_at' => (string) ( $entry['posted_at'] ?? '' ) !== '' ? (string) $entry['posted_at'] : gmdate( 'c' ),
							'via'        => 'meta.json',
						)
					);
				}
				continue;
			}

			if ( $known && ! self::is_plan( $known ) ) {
				continue;
			}

			$disabled = isset( $registry[ $key ] ) && empty( $registry[ $key ]['enabled'] );
			if ( 'skipped' === $raw || $disabled ) {
				unset( $current[ $key ] );
				continue;
			}

			$current[ $key ] = self::plan( $entry, $registry[ $key ] ?? null, $context, $known ?? array() );
		}

		return $current;
	}

	/**
	 * Planned or waiting for a person: recalculated from meta.json on every sync.
	 *
	 * @param array<string, string> $entry Index entry.
	 */
	private static function is_plan( array $entry ): bool {
		return in_array( $entry['status'] ?? '', array( self::PLANNED, self::MANUAL_PENDING ), true );
	}

	/**
	 * @param array<string, mixed>      $entry    meta.json entry.
	 * @param array<string, mixed>|null $platform Registry row.
	 * @param array<string, int|bool>   $context  See merge_package().
	 * @param array<string, string>     $known    Current entry.
	 * @return array<string, string>
	 */
	private static function plan( array $entry, ?array $platform, array $context, array $known ): array {
		$mode    = self::mode( $entry, $platform );
		$live_at = (int) ( $context['live_at'] ?? 0 );
		$delay   = (int) ( self::MODE_MANUAL === $mode ? ( $context['manual_delay'] ?? 0 ) : ( $context['api_delay'] ?? 0 ) );
		$due     = $live_at > 0 ? $live_at + $delay : 0;
		$auto    = self::MODE_MANUAL === $mode || ! empty( $context['auto_publish'] );
		$pending = self::MODE_MANUAL === $mode && $due > 0 && $due <= (int) ( $context['now'] ?? time() );

		return array_merge(
			self::merge_entry(
				$known,
				array(
					'mode'   => $mode,
					'status' => $pending ? self::MANUAL_PENDING : self::PLANNED,
					'run_at' => $due > 0 && $auto ? gmdate( 'c', $due ) : '',
					'via'    => 'meta.json',
				)
			),
			// A plan has no event of its own: the date stays stable across syncs.
			array( 'updated_at' => $pending ? gmdate( 'c', $due ) : (string) ( $known['updated_at'] ?? gmdate( 'c' ) ) )
		);
	}

	/**
	 * meta.json mode wins over the registry.
	 *
	 * @param array<string, mixed>      $entry    meta.json entry.
	 * @param array<string, mixed>|null $platform Registry row.
	 */
	public static function mode( array $entry, ?array $platform ): string {
		$mode = (string) ( $entry['mode'] ?? ( $platform['mode'] ?? '' ) );

		return self::MODE_API === $mode ? self::MODE_API : self::MODE_MANUAL;
	}

	/**
	 * Refresh a post from its meta.json. Drive hook: forwp_drive_document_imported( $post_id ).
	 *
	 * @param int $post_id Post id.
	 * @return bool False when the package could not be read: the index stays as it is.
	 */
	public static function sync_package( $post_id ): bool {
		$post_id = (int) $post_id;
		$package = ( new Package_Reader() )->package( $post_id );
		if ( ! is_array( $package ) ) {
			return false;
		}

		update_post_meta( $post_id, self::META, self::merge_package( self::get( $post_id ), $package, Platform_Registry::all( false ), self::context( $post_id, $package ) ) );

		return true;
	}

	/**
	 * @param int                  $post_id Post id.
	 * @param array<string, mixed> $package meta.json data.
	 * @return array<string, int|bool>
	 */
	private static function context( int $post_id, array $package ): array {
		$status = (string) get_post_status( $post_id );

		return array(
			'live_at'      => in_array( $status, array( 'publish', 'future' ), true ) ? (int) get_post_time( 'U', true, $post_id ) : 0,
			'now'          => time(),
			'api_delay'    => Package_Reader::delay_seconds( $package ),
			'manual_delay' => Package_Reader::delay_seconds( $package, (int) Settings::get( 'manual_delay_minutes', 1440 ) * MINUTE_IN_SECONDS ),
			'auto_publish' => (bool) Settings::get( 'auto_publish', false ),
		);
	}

	/**
	 * Build the index of an existing post: latest jobs, known publications, then meta.json.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, array<string, string>>
	 */
	public static function rebuild( int $post_id ): array {
		$index = self::get( $post_id );

		$seen = array();
		foreach ( ( new Job_Repository() )->recent( $post_id, 50 ) as $job ) {
			$platform = (string) $job->platform;
			if ( isset( $seen[ $platform ] ) ) {
				continue;
			}
			$seen[ $platform ]  = true;
			$index[ $platform ] = self::merge_entry( $index[ $platform ] ?? array(), self::fields_from_job( $job ) );
		}

		foreach ( ( new Posting_Service() )->published_map( $post_id ) as $platform => $row ) {
			$via                = (string) ( $row['via'] ?? self::MODE_API );
			$index[ $platform ] = self::merge_entry(
				$index[ $platform ] ?? array(),
				array(
					'status'     => Job_Repository::PUBLISHED,
					'url'        => (string) ( $row['url'] ?? '' ),
					'remote_id'  => (string) ( $row['id'] ?? '' ),
					'updated_at' => (string) ( $row['at'] ?? gmdate( 'c' ) ),
					'via'        => 'search' === $via ? 'manual' : $via,
				)
			);
		}

		update_post_meta( $post_id, self::META, $index );
		self::sync_package( $post_id );

		return self::get( $post_id );
	}
}
