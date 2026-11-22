<?php
/**
 * YouTube panels: channel totals, 28-day totals, daily views chart.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { channel, daily }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_yt_channel = $forwp_social_data['channel']['items'][0] ?? array();
$forwp_social_yt_stats   = is_array( $forwp_social_yt_channel['statistics'] ?? null ) ? $forwp_social_yt_channel['statistics'] : array();
$forwp_social_yt_daily   = is_array( $forwp_social_data['daily'] ?? null ) ? $forwp_social_data['daily'] : array();

// Daily report rows → [ day => [ metric => value ] ].
$forwp_social_yt_cols = array();
foreach ( (array) ( $forwp_social_yt_daily['columnHeaders'] ?? array() ) as $forwp_social_i => $forwp_social_col ) {
	$forwp_social_yt_cols[ (string) ( $forwp_social_col['name'] ?? $forwp_social_i ) ] = $forwp_social_i;
}
$forwp_social_yt_rows = array();
foreach ( (array) ( $forwp_social_yt_daily['rows'] ?? array() ) as $forwp_social_row ) {
	$forwp_social_day = (string) ( $forwp_social_row[ $forwp_social_yt_cols['day'] ?? 0 ] ?? '' );
	foreach ( $forwp_social_yt_cols as $forwp_social_name => $forwp_social_i ) {
		if ( 'day' !== $forwp_social_name ) {
			$forwp_social_yt_rows[ $forwp_social_day ][ $forwp_social_name ] = (float) ( $forwp_social_row[ $forwp_social_i ] ?? 0 );
		}
	}
}

$forwp_social_yt_sum = static function ( string $metric ) use ( $forwp_social_yt_rows ): float {
	return (float) array_sum( array_column( $forwp_social_yt_rows, $metric ) );
};
$forwp_social_yt_views = $forwp_social_yt_sum( 'views' );
// Average view duration weighted by views.
$forwp_social_yt_avg = 0.0;
if ( $forwp_social_yt_views > 0 ) {
	foreach ( $forwp_social_yt_rows as $forwp_social_r ) {
		$forwp_social_yt_avg += ( $forwp_social_r['averageViewDuration'] ?? 0 ) * ( $forwp_social_r['views'] ?? 0 );
	}
	$forwp_social_yt_avg /= $forwp_social_yt_views;
}

$forwp_social_num = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};

$forwp_social_yt_tiles = array(
	__( 'Subscribers', '4wp-social' )        => $forwp_social_num( $forwp_social_yt_stats['subscriberCount'] ?? null ),
	__( 'Total views', '4wp-social' )        => $forwp_social_num( $forwp_social_yt_stats['viewCount'] ?? null ),
	__( 'Videos', '4wp-social' )             => $forwp_social_num( $forwp_social_yt_stats['videoCount'] ?? null ),
);
$forwp_social_yt_period = array(
	__( 'Views', '4wp-social' )              => $forwp_social_num( $forwp_social_yt_views ),
	__( 'Minutes watched', '4wp-social' )    => $forwp_social_num( $forwp_social_yt_sum( 'estimatedMinutesWatched' ) ),
	__( 'Avg view duration', '4wp-social' )  => $forwp_social_yt_views > 0 ? gmdate( 'i:s', (int) round( $forwp_social_yt_avg ) ) : '—',
	__( 'Likes', '4wp-social' )              => $forwp_social_num( $forwp_social_yt_sum( 'likes' ) ),
	__( 'Comments', '4wp-social' )           => $forwp_social_num( $forwp_social_yt_sum( 'comments' ) ),
	__( 'Shares', '4wp-social' )             => $forwp_social_num( $forwp_social_yt_sum( 'shares' ) ),
	__( 'Net subscribers', '4wp-social' )    => $forwp_social_num( $forwp_social_yt_sum( 'subscribersGained' ) - $forwp_social_yt_sum( 'subscribersLost' ) ),
);
?>
<div class="forwp-social-yt">
	<?php if ( isset( $forwp_social_data['channel']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['channel']['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-yt__title"><?php esc_html_e( 'Channel', '4wp-social' ); ?></h3>
		<div class="forwp-social-yt__tiles">
			<?php foreach ( $forwp_social_yt_tiles as $forwp_social_label => $forwp_social_value ) : ?>
				<div class="forwp-social-yt__tile"><strong><?php echo esc_html( $forwp_social_value ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_yt_daily['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_yt_daily['error'] ); ?></p>
	<?php elseif ( $forwp_social_yt_rows ) : ?>
		<h3 class="forwp-social-yt__title">
			<?php
			$forwp_social_days = array_keys( $forwp_social_yt_rows );
			/* translators: 1: first day, 2: last day. */
			echo esc_html( sprintf( __( 'Last 28 days (%1$s → %2$s)', '4wp-social' ), reset( $forwp_social_days ), end( $forwp_social_days ) ) );
			?>
		</h3>
		<div class="forwp-social-yt__tiles">
			<?php foreach ( $forwp_social_yt_period as $forwp_social_label => $forwp_social_value ) : ?>
				<div class="forwp-social-yt__tile"><strong><?php echo esc_html( $forwp_social_value ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<h3 class="forwp-social-yt__title"><?php esc_html_e( 'Views by day', '4wp-social' ); ?></h3>
		<?php
		$forwp_social_max = max( 1, max( array_column( $forwp_social_yt_rows, 'views' ) ?: array( 0 ) ) );
		$forwp_social_n   = count( $forwp_social_yt_rows );
		$forwp_social_w   = 100 / $forwp_social_n;
		?>
		<svg class="forwp-social-yt__chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Views by day', '4wp-social' ); ?>">
			<?php
			$forwp_social_i = 0;
			foreach ( $forwp_social_yt_rows as $forwp_social_day => $forwp_social_r ) :
				$forwp_social_h = 38 * ( ( $forwp_social_r['views'] ?? 0 ) / $forwp_social_max );
				?>
				<rect x="<?php echo esc_attr( (string) ( $forwp_social_i * $forwp_social_w + $forwp_social_w * 0.15 ) ); ?>" y="<?php echo esc_attr( (string) ( 40 - $forwp_social_h ) ); ?>" width="<?php echo esc_attr( (string) ( $forwp_social_w * 0.7 ) ); ?>" height="<?php echo esc_attr( (string) $forwp_social_h ); ?>"><title><?php echo esc_html( $forwp_social_day . ': ' . number_format_i18n( $forwp_social_r['views'] ?? 0 ) ); ?></title></rect>
				<?php
				++$forwp_social_i;
			endforeach;
			?>
		</svg>
		<div class="forwp-social-yt__axis"><span><?php echo esc_html( (string) reset( $forwp_social_days ) ); ?></span><span><?php echo esc_html( 'max ' . number_format_i18n( $forwp_social_max ) ); ?></span><span><?php echo esc_html( (string) end( $forwp_social_days ) ); ?></span></div>
	<?php endif; ?>
</div>
