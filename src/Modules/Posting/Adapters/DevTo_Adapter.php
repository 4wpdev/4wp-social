<?php
/**
 * DEV.to: publish or update an article from a meta.json package file.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Posting\Adapters;

use ForWP\Social\Auth\DevTo_Account;
use ForWP\Social\Contracts\Article_Adapter_Interface;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;
use ForWP\Social\Modules\Posting\Posting_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Synchronous. Privacy "public" publishes; any other privacy, or "published: false" in the file, saves a DEV.to draft.
 */
final class DevTo_Adapter implements Article_Adapter_Interface {

	public const SLUG = DevTo_Account::SLUG;

	/**
	 * DEV.to accepts up to 4 tags.
	 */
	private const MAX_TAGS = 4;

	public function get_slug(): string {
		return self::SLUG;
	}

	public function get_label(): string {
		return 'DEV.to';
	}

	public function is_ready(): bool {
		return DevTo_Account::is_connected();
	}

	public function publish( Media_Package $package ): Publish_Result {
		if ( ! $this->is_ready() ) {
			return Publish_Result::failed( __( 'The DEV.to account is not connected.', '4wp-social' ), false );
		}

		$client   = DevTo_Account::client();
		$payload  = self::payload( $package );
		$existing = self::existing_id( $package->post_id() );

		// A post that is already on DEV.to ("Publish again") updates its article instead of duplicating it.
		$response = $existing ? $client->update_article( $existing, $payload ) : $client->create_article( $payload );
		if ( $existing && is_wp_error( $response ) && 404 === self::status( $response ) ) {
			$response = $client->create_article( $payload );
		}

		if ( is_wp_error( $response ) ) {
			return Publish_Result::failed( $response->get_error_message(), self::is_retryable( $response ) );
		}

		$id = (string) ( $response['id'] ?? $existing );

		return Publish_Result::published( '0' !== $id ? $id : '', (string) ( $response['url'] ?? '' ) );
	}

	public function check( string $reference, Media_Package $package ): Publish_Result {
		// publish() never returns processing.
		return Publish_Result::published( $reference, '' );
	}

	/**
	 * Fields of POST /api/articles.
	 *
	 * @param Media_Package $package Package.
	 * @return array<string, mixed>
	 */
	public static function payload( Media_Package $package ): array {
		$article = $package->article();
		$payload = array(
			'title'         => $package->title(),
			'body_markdown' => (string) ( $article['body'] ?? '' ),
			'published'     => 'public' === $package->privacy() && empty( $article['draft'] ),
			'canonical_url' => (string) ( $article['canonical_url'] ?? '' ),
			'tags'          => implode( ',', self::tags( $package->tags() ) ),
		);

		$optional = array(
			'description' => $package->description(),
			'main_image'  => (string) ( $article['main_image'] ?? '' ),
			'series'      => (string) ( $article['series'] ?? '' ),
		);

		return array_merge( $payload, array_filter( $optional ) );
	}

	/**
	 * Lowercase letters and digits only, no duplicates, first 4.
	 *
	 * @param array<int, string> $tags Tags.
	 * @return array<int, string>
	 */
	public static function tags( array $tags ): array {
		$clean = array();
		foreach ( $tags as $tag ) {
			$clean[] = (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $tag ) );
		}

		return array_slice( array_values( array_unique( array_filter( $clean ) ) ), 0, self::MAX_TAGS );
	}

	/**
	 * Rate limits and server errors may pass. A lost connection is not retried: the article may already exist.
	 *
	 * @param WP_Error $error Error.
	 */
	public static function is_retryable( WP_Error $error ): bool {
		$status = self::status( $error );

		return 429 === $status || $status >= 500;
	}

	/**
	 * @param WP_Error $error Error.
	 */
	private static function status( WP_Error $error ): int {
		$data = $error->get_error_data();

		return is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
	}

	/**
	 * DEV.to article id already stored on the post.
	 *
	 * @param int $post_id Post id.
	 */
	private static function existing_id( int $post_id ): int {
		$map = get_post_meta( $post_id, Posting_Service::PUBLISHED_META, true );

		return is_array( $map ) ? (int) ( $map[ self::SLUG ]['id'] ?? 0 ) : 0;
	}
}
