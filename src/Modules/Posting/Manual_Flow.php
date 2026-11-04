<?php
/**
 * Manual platforms: after the delay they wait for a person, with a Telegram reminder.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting;

use ForWP\Booking\Channels\Telegram_Channel;
use ForWP\Social\Core\Settings;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * One-shot cron after the original goes live, same mechanism as scheduled posts.
 */
final class Manual_Flow {

	public const HOOK = 'forwp_social_manual_due';

	/**
	 * @return void
	 */
	public static function boot(): void {
		// After 4WP Drive (10) has written original.url and Drive_Bridge (20) has queued the API platforms.
		add_action( 'transition_post_status', array( self::class, 'on_status' ), 30, 3 );
		add_action( self::HOOK, array( self::class, 'due' ) );
	}

	/**
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public static function on_status( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || ! ( new Package_Reader() )->is_package_post( (int) $post->ID ) ) {
			return;
		}

		$args = array( (int) $post->ID );
		$next = wp_next_scheduled( self::HOOK, $args );

		if ( 'publish' !== $new_status ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::HOOK, $args );
			}
			return;
		}
		if ( 'publish' === $old_status || $next ) {
			return;
		}

		Status_Index::sync_package( (int) $post->ID );

		$package = ( new Package_Reader() )->package( (int) $post->ID );
		$delay   = Package_Reader::delay_seconds( is_array( $package ) ? $package : array(), (int) Settings::get( 'manual_delay_minutes', 1440 ) * MINUTE_IN_SECONDS );
		wp_schedule_single_event( time() + max( 1, $delay ), self::HOOK, $args );
	}

	/**
	 * Cron: the delay has passed.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function due( $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		Status_Index::sync_package( $post_id );
		if ( Settings::get( 'manual_notify', false ) ) {
			self::notify( $post_id );
		}
	}

	/**
	 * Send the Telegram list now.
	 *
	 * @param int $post_id Post id.
	 * @return true|WP_Error
	 */
	public static function notify( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'forwp_social_no_post', __( 'Post not found.', '4wp-social' ), array( 'status' => 404 ) );
		}
		if ( ! class_exists( Telegram_Channel::class ) ) {
			$error = new WP_Error( 'forwp_social_telegram', __( 'Telegram channel is not available.', '4wp-social' ), array( 'status' => 502 ) );
			self::remember( $post_id, array( 'notify_error' => $error->get_error_message() ) );
			return $error;
		}

		$result = ( new Telegram_Channel() )->send_html( self::message( $post ) );
		if ( is_wp_error( $result ) ) {
			self::remember( $post_id, array( 'notify_error' => $result->get_error_message() ) );
			return $result;
		}

		self::remember(
			$post_id,
			array(
				'notified_at'  => gmdate( 'c' ),
				'notify_error' => '',
			)
		);

		return true;
	}

	/**
	 * Keep the reminder result on every manual platform of the post.
	 *
	 * @param int                   $post_id Post id.
	 * @param array<string, string> $fields  Fields.
	 * @return void
	 */
	private static function remember( int $post_id, array $fields ): void {
		$index = Status_Index::get( $post_id );
		foreach ( $index as $key => $entry ) {
			if ( Status_Index::MODE_MANUAL === ( $entry['mode'] ?? '' ) ) {
				$index[ $key ] = array_merge( $entry, $fields );
			}
		}
		update_post_meta( $post_id, Status_Index::META, $index );
	}

	/**
	 * Telegram HTML: the post and its manual platforms.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function message( WP_Post $post ): string {
		$index = Status_Index::get( (int) $post->ID );
		$lines = array(
			'<b>' . esc_html__( 'Share this material', '4wp-social' ) . '</b>',
			'<a href="' . esc_attr( (string) get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>',
			'',
		);

		foreach ( Platform_Registry::all() as $key => $platform ) {
			$entry = $index[ $key ] ?? null;
			if ( ! is_array( $entry ) || Status_Index::MODE_MANUAL !== ( $entry['mode'] ?? '' ) ) {
				continue;
			}
			$link = '' !== (string) ( $entry['url'] ?? '' ) ? (string) $entry['url'] : (string) $platform['url'];
			if ( '' === $link ) {
				continue;
			}
			$lines[] = '<a href="' . esc_attr( $link ) . '">' . esc_html( (string) $platform['label'] ) . '</a> — ' . esc_html( (string) $entry['status'] );
		}

		return implode( "\n", $lines );
	}
}
