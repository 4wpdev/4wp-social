<?php
/**
 * One list of platforms. A platform is manual or bound to one connector of the plugin.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The option is the whole list; the defaults only seed it until the first save, so a platform can be deleted.
 */
final class Platform_Registry {

	public const OPTION = 'forwp_social_platforms';

	/**
	 * Starting list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$rows = array(
			array( 'youtube.com', 'YouTube', 'youtube.com', 'https://studio.youtube.com/', true ),
			array( 'tiktok.com', 'TikTok', 'tiktok.com', 'https://www.tiktok.com/upload', true ),
			array( 'bsky.app', 'Bluesky', 'bsky.app', 'https://bsky.app/', true ),
			array( 'dev.to', 'DEV.to', 'dev.to', 'https://dev.to/new', true ),
			array( 'x.com', 'X.com', '', 'https://x.com/compose/post', true ),
			array( 'medium.com', 'Medium', '', 'https://medium.com/new-story', true ),
			array( 'hashnode.com', 'Hashnode', '', 'https://hashnode.com/draft', true ),
			array( 'daily.dev', 'daily.dev', '', 'https://app.daily.dev/', true ),
			array( 'linkedin.com', 'LinkedIn', '', 'https://www.linkedin.com/feed/', true ),
			array( 'reddit.com', 'Reddit', '', 'https://www.reddit.com/submit', false ),
			array( 'facebook.com', 'Facebook', 'facebook.com', 'https://www.facebook.com/', false ),
			array( 'instagram.com', 'Instagram', 'instagram.com', 'https://www.instagram.com/', false ),
			array( 'pinterest.com', 'Pinterest', 'pinterest.com', 'https://www.pinterest.com/pin-creation-tool/', true ),
		);

		$out = array();
		foreach ( $rows as $order => $row ) {
			$out[ $row[0] ] = array(
				'key'       => $row[0],
				'label'     => $row[1],
				'connector' => $row[2],
				'mode'      => '' !== $row[2] ? Status_Index::MODE_API : Status_Index::MODE_MANUAL,
				'url'       => $row[3],
				'format'    => '',
				'icon'      => '',
				'enabled'   => $row[4],
				'order'     => $order,
			);
		}

		return $out;
	}

	/**
	 * Platforms added after a site saved its list (shown once, disabled; the user can delete them).
	 */
	private const ADDED = array( 'pinterest.com' );

	/**
	 * Add new default platforms to a saved list, once per key.
	 *
	 * @param array<string, array<string, mixed>> $rows Saved rows.
	 * @return array<string, array<string, mixed>>
	 */
	private static function with_added( array $rows ): array {
		$seen = (array) get_option( self::OPTION . '_added', array() );
		$new  = array_diff( self::ADDED, $seen );
		if ( ! $new ) {
			return $rows;
		}
		$defaults = self::defaults();
		$order    = array_map( static fn( array $row ): int => (int) ( $row['order'] ?? 0 ), $rows );
		foreach ( $new as $key ) {
			if ( ! isset( $rows[ $key ] ) && isset( $defaults[ $key ] ) ) {
				$rows[ $key ] = array_merge( $defaults[ $key ], array( 'order' => $order ? max( $order ) + 1 : 0 ) );
			}
		}
		update_option( self::OPTION, $rows );
		update_option( self::OPTION . '_added', array_values( array_unique( array_merge( $seen, $new ) ) ), false );

		return $rows;
	}

	/**
	 * Platforms in order.
	 *
	 * @param bool $enabled_only Skip switched-off platforms.
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( bool $enabled_only = true ): array {
		$stored = get_option( self::OPTION, null );
		$rows   = is_array( $stored ) ? self::with_added( $stored ) : self::defaults();

		uasort(
			$rows,
			static function ( array $a, array $b ): int {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return $enabled_only ? array_filter( $rows, static fn( array $row ): bool => ! empty( $row['enabled'] ) ) : $rows;
	}

	/**
	 * One platform.
	 *
	 * @param string $key Platform key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		return self::all( false )[ $key ] ?? null;
	}

	/**
	 * Add a platform to the end of the list.
	 *
	 * @param array<string, mixed> $input Fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $input ) {
		$rows = self::all( false );
		$key  = self::clean_key( (string) ( $input['key'] ?? '' ) );
		if ( '' === $key ) {
			return new WP_Error( 'forwp_social_platform_key', __( 'The key can have only letters, digits, dots and hyphens, e.g. example.com.', '4wp-social' ), array( 'status' => 400 ) );
		}
		if ( isset( $rows[ $key ] ) ) {
			return new WP_Error( 'forwp_social_platform_exists', __( 'A platform with this key already exists.', '4wp-social' ), array( 'status' => 409 ) );
		}

		$order = array_map( static fn( array $row ): int => (int) $row['order'], $rows );

		return self::store( $rows, $key, array_merge( $input, array( 'order' => $order ? max( $order ) + 1 : 0 ) ) );
	}

	/**
	 * Change a platform. The key stays: statuses on posts and meta.json refer to it.
	 *
	 * @param string               $key   Platform key.
	 * @param array<string, mixed> $input Changed fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( string $key, array $input ) {
		$rows = self::all( false );
		if ( ! isset( $rows[ $key ] ) ) {
			return new WP_Error( 'forwp_social_platform_missing', __( 'Platform not found.', '4wp-social' ), array( 'status' => 404 ) );
		}

		return self::store( $rows, $key, array_merge( $rows[ $key ], $input ) );
	}

	/**
	 * Validate the connector and save one row.
	 *
	 * @param array<string, array<string, mixed>> $rows  Current rows.
	 * @param string                              $key   Platform key.
	 * @param array<string, mixed>                $input Fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function store( array $rows, string $key, array $input ) {
		$connector = (string) ( $input['connector'] ?? '' );
		if ( '' !== $connector ) {
			if ( ! isset( Adapter_Registry::connectors()[ $connector ] ) ) {
				return new WP_Error( 'forwp_social_connector', __( 'This connector is not available.', '4wp-social' ), array( 'status' => 400 ) );
			}
			foreach ( $rows as $other => $row ) {
				if ( $other !== $key && ( $row['connector'] ?? '' ) === $connector ) {
					/* translators: %s: platform name. */
					return new WP_Error( 'forwp_social_connector_taken', sprintf( __( 'This connector is already bound to %s.', '4wp-social' ), (string) $row['label'] ), array( 'status' => 409 ) );
				}
			}
		}

		$rows[ $key ] = array_merge( $input, array( 'key' => $key ) );
		self::save( $rows );

		return self::get( $key );
	}

	/**
	 * Remove a platform.
	 *
	 * @param string $key Platform key.
	 * @return true|WP_Error
	 */
	public static function delete( string $key ) {
		$rows = self::all( false );
		if ( ! isset( $rows[ $key ] ) ) {
			return new WP_Error( 'forwp_social_platform_missing', __( 'Platform not found.', '4wp-social' ), array( 'status' => 404 ) );
		}
		unset( $rows[ $key ] );
		self::save( $rows );

		return true;
	}

	/**
	 * New order of the list. Keys not named go to the end.
	 *
	 * @param array<int, string> $keys Platform keys.
	 * @return void
	 */
	public static function reorder( array $keys ): void {
		$rows  = self::all( false );
		$order = 0;
		foreach ( array_unique( array_merge( $keys, array_keys( $rows ) ) ) as $key ) {
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['order'] = $order++;
			}
		}
		self::save( $rows );
	}

	/**
	 * Lay rows over the list without touching what they do not name (connector, empty url). Pure.
	 *
	 * @param array<string, array<string, mixed>> $current Current rows.
	 * @param array<int, array<string, mixed>>    $rows    New rows.
	 * @return array<string, array<string, mixed>>
	 */
	public static function merge_rows( array $current, array $rows ): array {
		foreach ( $rows as $row ) {
			$key = (string) $row['key'];
			if ( isset( $current[ $key ] ) ) {
				unset( $row['order'] );
				if ( '' === (string) ( $row['url'] ?? '' ) ) {
					unset( $row['url'] );
				}
			}
			$current[ $key ] = array_merge( $current[ $key ] ?? array( 'connector' => '' ), $row );
		}

		return $current;
	}

	/**
	 * Clean rows. Invalid keys are dropped; a connector stays with the first platform that has it.
	 *
	 * @param array<int|string, mixed> $input Raw rows.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize_rows( array $input ): array {
		$out  = array();
		$used = array();
		foreach ( $input as $index => $row ) {
			$key = is_array( $row ) ? self::clean_key( (string) ( $row['key'] ?? '' ) ) : '';
			if ( '' === $key ) {
				continue;
			}
			$label     = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$connector = sanitize_text_field( (string) ( $row['connector'] ?? '' ) );
			if ( isset( $used[ $connector ] ) ) {
				$connector = '';
			}
			if ( '' !== $connector ) {
				$used[ $connector ] = true;
			}

			$out[ $key ] = array(
				'key'       => $key,
				'label'     => '' !== $label ? $label : $key,
				'connector' => $connector,
				'mode'      => '' !== $connector ? Status_Index::MODE_API : Status_Index::MODE_MANUAL,
				'url'       => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
				'format'    => sanitize_text_field( (string) ( $row['format'] ?? '' ) ),
				'icon'      => esc_url_raw( (string) ( $row['icon'] ?? '' ) ),
				'enabled'   => ! empty( $row['enabled'] ),
				'order'     => isset( $row['order'] ) ? (int) $row['order'] : (int) $index,
			);
		}

		return $out;
	}

	/**
	 * Save the whole list.
	 *
	 * @param array<int|string, mixed> $input Raw rows.
	 * @return void
	 */
	public static function save( array $input ): void {
		update_option( self::OPTION, self::sanitize_rows( $input ), false );
		// A list saved by the user is the whole list: new defaults are not added to it.
		update_option( self::OPTION . '_added', self::ADDED, false );
	}

	/**
	 * Valid platform key or ''.
	 *
	 * @param string $key Raw key.
	 */
	private static function clean_key( string $key ): string {
		$key = strtolower( trim( sanitize_text_field( $key ) ) );

		return preg_match( '/^[a-z0-9][a-z0-9.\-]*$/', $key ) ? $key : '';
	}
}
