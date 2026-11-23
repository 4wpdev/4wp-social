<?php
/**
 * LinkedIn: manual import of the analytics export (.xlsx from the profile, or a CSV sheet).
 * Days go to Metrics_Store (each import adds its days), posts to an option, the rest to the snapshot.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Modules\Analytics\Sources;

use ForWP\Social\Contracts\Analytics_Daily_Interface;
use ForWP\Social\Modules\Analytics\Metrics_Store;
use ForWP\Social\Modules\Analytics\Xlsx_Reader;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * LinkedIn → Analytics → Export (Aggregate analytics .xlsx).
 */
final class LinkedIn_Source extends Manual_Source implements Analytics_Daily_Interface {

	public const SLUG         = 'linkedin.com';
	public const POSTS_OPTION = 'forwp_social_linkedin_posts';

	/**
	 * Recent-posts sheet header => stored key.
	 */
	private const POST_COLUMNS = array(
		'impressions'     => 'impressions',
		'members reached' => 'reached',
		'views'           => 'views',
		'reactions'       => 'reactions',
		'comments'        => 'comments',
		'reposts'         => 'reposts',
		'saves'           => 'saves',
		'sends'           => 'sends',
	);

	/**
	 * LinkedIn card.
	 */
	public function __construct() {
		parent::__construct(
			self::SLUG,
			__( 'LinkedIn', '4wp-social' ),
			__( 'Manual import: LinkedIn → your profile → Analytics → Export (.xlsx). Each file adds its days; upload a new one every few weeks. Post numbers are totals for the export period.', '4wp-social' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions(): array {
		return array( 'xlsx', 'csv' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $path Temporary file path.
	 * @param string $name Original file name.
	 */
	public function import( string $path, string $name ) {
		if ( 'xlsx' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return parent::import( $path, $name );
		}

		$sheets = Xlsx_Reader::sheets( $path );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}
		$data = self::parse( $sheets );
		if ( ! $data['daily'] && ! $data['posts'] ) {
			return new WP_Error( 'forwp_social_analytics_import', __( 'No LinkedIn analytics found in this file. Upload the Aggregate analytics export.', '4wp-social' ) );
		}

		Metrics_Store::upsert( self::SLUG, $data['daily'] );
		$posts = get_option( self::POSTS_OPTION, array() );
		update_option( self::POSTS_OPTION, array_merge( is_array( $posts ) ? $posts : array(), $data['posts'] ), false );
		update_option( Metrics_Store::ITEMS_VERSION, (string) time(), false );

		return array(
			'file'         => $name,
			'period'       => $data['period'],
			'account'      => $data['account'],
			'demographics' => $data['demographics'],
			'days'         => count( $data['daily'] ),
			'posts'        => count( $data['posts'] ),
		);
	}

	/**
	 * Workbook sheets → days, posts, account totals, demographics.
	 *
	 * @param array<string, array<int, array<int, string>>> $sheets Sheet name => rows.
	 * @return array{period: string, daily: array<string, array<string, float>>, posts: array<string, array<string, mixed>>, account: array<string, mixed>, demographics: array<string, array<int, array{0: string, 1: string}>>}
	 */
	public static function parse( array $sheets ): array {
		$sheets = array_change_key_case( $sheets, CASE_UPPER );
		$out    = array(
			'period'       => '',
			'daily'        => array(),
			'posts'        => array(),
			'account'      => array(),
			'demographics' => array(),
		);

		// DISCOVERY: period, impressions, members reached.
		foreach ( $sheets['DISCOVERY'] ?? array() as $row ) {
			$label = strtolower( trim( (string) ( $row[0] ?? '' ) ) );
			if ( 'overall performance' === $label ) {
				$out['period'] = trim( (string) ( $row[1] ?? '' ) );
			} elseif ( 'impressions' === $label ) {
				$out['account']['impressions'] = self::number( (string) ( $row[1] ?? '' ) );
			} elseif ( 'members reached' === $label ) {
				$out['account']['reached'] = self::number( (string) ( $row[1] ?? '' ) );
			}
		}

		// ENGAGEMENT: Date | Impressions | Engagements.
		foreach ( self::table( $sheets['ENGAGEMENT'] ?? array(), 'date' ) as $row ) {
			$day = self::day( $row['date'] ?? '' );
			if ( null !== $day ) {
				$out['daily'][ $day ]['impressions'] = self::number( $row['impressions'] ?? '' );
				$out['daily'][ $day ]['engagements'] = self::number( $row['engagements'] ?? '' );
			}
		}

		// FOLLOWERS: "Total followers on m/d/Y" | total; Date | New followers.
		foreach ( $sheets['FOLLOWERS'] ?? array() as $row ) {
			if ( preg_match( '#^total followers on (.+)$#i', trim( (string) ( $row[0] ?? '' ) ), $m ) ) {
				$out['account']['followers']    = self::number( (string) ( $row[1] ?? '' ) );
				$out['account']['followers_on'] = (string) self::day( $m[1] );
			}
		}
		foreach ( self::table( $sheets['FOLLOWERS'] ?? array(), 'date' ) as $row ) {
			$day = self::day( $row['date'] ?? '' );
			if ( null !== $day ) {
				$out['daily'][ $day ]['followers'] = self::number( $row['new followers'] ?? '' );
			}
		}
		ksort( $out['daily'] );

		// TOP POSTS: side-by-side blocks "Post URL | Post Publish Date | <metric>".
		$top    = $sheets['TOP POSTS'] ?? array();
		$header = self::header_row( $top, 'post url' );
		if ( null !== $header ) {
			foreach ( $top[ $header ] as $col => $title ) {
				if ( 'post url' !== strtolower( trim( (string) $title ) ) ) {
					continue;
				}
				$metric = strtolower( trim( (string) ( $top[ $header ][ $col + 2 ] ?? '' ) ) );
				foreach ( array_slice( $top, $header + 1, null, true ) as $row ) {
					$url = trim( (string) ( $row[ $col ] ?? '' ) );
					if ( '' === $url || '' === $metric ) {
						continue;
					}
					$out['posts'][ $url ]            = $out['posts'][ $url ] ?? self::post( $url, (string) ( $row[ $col + 1 ] ?? '' ) );
					$out['posts'][ $url ][ $metric ] = self::number( (string) ( $row[ $col + 2 ] ?? '' ) );
				}
			}
		}

		// MOST RECENT POSTS: full numbers per post.
		foreach ( self::table( $sheets['MOST RECENT POSTS'] ?? array(), 'post url' ) as $row ) {
			$url = trim( (string) ( $row['post url'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$post         = $out['posts'][ $url ] ?? self::post( $url, (string) ( $row['post publish date'] ?? '' ) );
			$post['type'] = trim( (string) ( $row['post type'] ?? '' ) );
			foreach ( self::POST_COLUMNS as $title => $key ) {
				if ( '' !== trim( (string) ( $row[ $title ] ?? '' ) ) ) {
					$post[ $key ] = self::number( (string) $row[ $title ] );
				}
			}
			if ( ! isset( $post['engagements'] ) ) {
				$post['engagements'] = (float) array_sum( array_intersect_key( $post, array_flip( array( 'reactions', 'comments', 'reposts', 'saves', 'sends' ) ) ) );
			}
			$out['posts'][ $url ] = $post;
		}

		// AUDIENCE DEMOGRAPHICS: Category | Value | Percentage.
		foreach ( self::table( $sheets['AUDIENCE DEMOGRAPHICS'] ?? array(), 'top demographics' ) as $row ) {
			$group = trim( (string) ( $row['top demographics'] ?? '' ) );
			if ( '' !== $group ) {
				$out['demographics'][ $group ][] = array( trim( (string) ( $row['value'] ?? '' ) ), trim( (string) ( $row['percentage'] ?? '' ) ) );
			}
		}

		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 */
	public function daily( string $start, string $end ) {
		return Metrics_Store::rows( self::SLUG, $start, $end );
	}

	/**
	 * {@inheritDoc}
	 */
	public function metrics(): array {
		return array(
			'impressions' => array(
				'label' => __( 'Impressions', '4wp-social' ),
				'agg'   => 'sum',
				'chart' => true,
			),
			'engagements' => array(
				'label' => __( 'Engagements', '4wp-social' ),
				'agg'   => 'sum',
			),
			'followers'   => array(
				'label' => __( 'New followers', '4wp-social' ),
				'agg'   => 'sum',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function history_days(): int {
		return 1;
	}

	/**
	 * Posts published in the period (numbers are totals for the export period), most impressions first.
	 *
	 * @param string $start First day (Y-m-d).
	 * @param string $end   Last day (Y-m-d).
	 * @param int    $limit Max posts.
	 */
	public function items( string $start, string $end, int $limit ) {
		$posts = get_option( self::POSTS_OPTION, array() );
		$out   = array();
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$day = substr( (string) ( $post['published_at'] ?? '' ), 0, 10 );
			if ( $day >= $start && $day <= $end ) {
				$out[] = $post;
			}
		}
		usort(
			$out,
			static function ( $a, $b ): int {
				return ( $b['impressions'] ?? 0 ) <=> ( $a['impressions'] ?? 0 );
			}
		);

		return array_slice( $out, 0, max( 1, $limit ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function item_columns(): array {
		$out = array();
		foreach ( array(
			'impressions' => __( 'Impressions', '4wp-social' ),
			'engagements' => __( 'Engagements', '4wp-social' ),
			'reached'     => __( 'Reached', '4wp-social' ),
			'reactions'   => __( 'Reactions', '4wp-social' ),
			'comments'    => __( 'Comments', '4wp-social' ),
		) as $key => $label ) {
			$out[ $key ] = array(
				'label'  => $label,
				'format' => 'num',
			);
		}

		return $out;
	}

	/**
	 * New post record from its URL and publish date.
	 *
	 * @param string $url  Post URL.
	 * @param string $date Publish date (m/d/Y).
	 * @return array<string, mixed>
	 */
	private static function post( string $url, string $date ): array {
		$day = self::day( $date );

		return array(
			'id'           => $url,
			'url'          => $url,
			'title'        => self::title( $url ),
			'published_at' => null !== $day ? $day . 'T12:00:00+00:00' : '',
		);
	}

	/**
	 * Readable title from a post URL: /posts/user_some-words-activity-123 → "some words".
	 *
	 * @param string $url Post URL.
	 */
	public static function title( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( preg_match( '#/posts/[^_/]+_(.+?)(?:-(?:activity|ugcPost|share)-\d+.*)?/?$#i', $path, $m ) ) {
			return trim( str_replace( '-', ' ', rawurldecode( $m[1] ) ) );
		}

		return __( '(repost)', '4wp-social' );
	}

	/**
	 * Rows under the header row (first cell = `$first`), keyed by lowercased header.
	 *
	 * @param array<int, array<int, string>> $rows  Sheet rows.
	 * @param string                         $first Header text (lowercase) that marks the header row.
	 * @return array<int, array<string, string>>
	 */
	private static function table( array $rows, string $first ): array {
		$header = self::header_row( $rows, $first );
		if ( null === $header ) {
			return array();
		}
		$keys = array_map(
			static function ( $h ): string {
				return strtolower( trim( (string) $h ) );
			},
			$rows[ $header ]
		);

		$out = array();
		foreach ( array_slice( $rows, $header + 1, null, true ) as $row ) {
			$item = array();
			foreach ( $keys as $col => $key ) {
				if ( '' !== $key && ! isset( $item[ $key ] ) ) {
					$item[ $key ] = (string) ( $row[ $col ] ?? '' );
				}
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Index of the first row whose first cell is `$first` (lowercase), or null.
	 *
	 * @param array<int, array<int, string>> $rows  Sheet rows.
	 * @param string                         $first Header text.
	 */
	private static function header_row( array $rows, string $first ): ?int {
		foreach ( $rows as $i => $row ) {
			if ( strtolower( trim( (string) ( $row[0] ?? '' ) ) ) === $first ) {
				return (int) $i;
			}
		}

		return null;
	}

	/**
	 * "m/d/Y" (or an Excel serial day) → Y-m-d, or null.
	 *
	 * @param string $text Cell text.
	 */
	public static function day( string $text ): ?string {
		$text = trim( $text );
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m ) && checkdate( (int) $m[1], (int) $m[2], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[1], $m[2] );
		}
		if ( is_numeric( $text ) && (float) $text > 20000 && (float) $text < 80000 ) {
			return gmdate( 'Y-m-d', (int) round( ( (float) $text - 25569 ) * DAY_IN_SECONDS ) );
		}

		return null;
	}

	/**
	 * "1,234" → 1234.0; empty or non-numeric → 0.
	 *
	 * @param string $text Cell text.
	 */
	private static function number( string $text ): float {
		$text = str_replace( array( ',', ' ' ), '', trim( $text ) );

		return is_numeric( $text ) ? (float) $text : 0.0;
	}
}
