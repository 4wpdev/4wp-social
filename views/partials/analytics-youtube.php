<?php
/**
 * YouTube panels: channel totals, 28-day totals, daily views chart, videos table.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { channel, daily, videos }.
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
<div class="forwp-social-an">
	<?php if ( isset( $forwp_social_data['channel']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['channel']['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Channel', '4wp-social' ); ?></h3>
		<div class="forwp-social-an__tiles">
			<?php foreach ( $forwp_social_yt_tiles as $forwp_social_label => $forwp_social_value ) : ?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_value ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_yt_daily['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_yt_daily['error'] ); ?></p>
	<?php elseif ( $forwp_social_yt_rows ) : ?>
		<h3 class="forwp-social-an__title">
			<?php
			$forwp_social_days = array_keys( $forwp_social_yt_rows );
			/* translators: 1: first day, 2: last day. */
			echo esc_html( sprintf( __( 'Last 28 days (%1$s → %2$s)', '4wp-social' ), reset( $forwp_social_days ), end( $forwp_social_days ) ) );
			?>
		</h3>
		<div class="forwp-social-an__tiles">
			<?php foreach ( $forwp_social_yt_period as $forwp_social_label => $forwp_social_value ) : ?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_value ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Views by day', '4wp-social' ); ?></h3>
		<?php
		$forwp_social_max = max( 1, max( array_column( $forwp_social_yt_rows, 'views' ) ?: array( 0 ) ) );
		$forwp_social_n   = count( $forwp_social_yt_rows );
		$forwp_social_w   = 100 / $forwp_social_n;
		?>
		<svg class="forwp-social-an__chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Views by day', '4wp-social' ); ?>">
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
		<div class="forwp-social-an__axis"><span><?php echo esc_html( (string) reset( $forwp_social_days ) ); ?></span><span><?php echo esc_html( 'max ' . number_format_i18n( $forwp_social_max ) ); ?></span><span><?php echo esc_html( (string) end( $forwp_social_days ) ); ?></span></div>
	<?php endif; ?>
	<?php $forwp_social_yt_videos = is_array( $forwp_social_data['videos'] ?? null ) ? $forwp_social_data['videos'] : array(); ?>
	<?php if ( isset( $forwp_social_yt_videos['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_yt_videos['error'] ); ?></p>
	<?php elseif ( ! empty( $forwp_social_yt_videos['rows'] ) ) : ?>
		<?php
		$forwp_social_vrows  = (array) $forwp_social_yt_videos['rows'];
		$forwp_social_vtotal = max( 1, (float) array_sum( array_column( $forwp_social_vrows, 'views' ) ) );
		$forwp_social_vmin   = (float) array_sum( array_column( $forwp_social_vrows, 'estimatedMinutesWatched' ) );
		$forwp_social_mmss   = static function ( $sec ): string {
			$sec = (int) round( (float) $sec );
			return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
		};
		?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: 1: number of videos, 2: first day, 3: last day. */
			echo esc_html( sprintf( __( 'Videos (%1$d) · %2$s → %3$s', '4wp-social' ), count( $forwp_social_vrows ), (string) ( $forwp_social_yt_videos['start'] ?? '' ), (string) ( $forwp_social_yt_videos['end'] ?? '' ) ) );
			?>
		</h3>
		<div class="forwp-social-an__table-wrap">
			<table class="widefat striped forwp-social-an__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Video', '4wp-social' ); ?></th>
						<th><?php esc_html_e( 'Published', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Views', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Watch time (h)', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Avg view', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Likes', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Comments', '4wp-social' ); ?></th>
						<th class="num"><?php esc_html_e( 'Subscribers', '4wp-social' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="forwp-social-an__total">
						<td><strong><?php esc_html_e( 'Total', '4wp-social' ); ?></strong></td>
						<td></td>
						<td class="num"><strong><?php echo esc_html( number_format_i18n( $forwp_social_vtotal ) ); ?></strong></td>
						<td class="num"><strong><?php echo esc_html( number_format_i18n( $forwp_social_vmin / 60, 1 ) ); ?></strong></td>
						<td></td>
						<td class="num"><strong><?php echo esc_html( number_format_i18n( array_sum( array_column( $forwp_social_vrows, 'likes' ) ) ) ); ?></strong></td>
						<td class="num"><strong><?php echo esc_html( number_format_i18n( array_sum( array_column( $forwp_social_vrows, 'comments' ) ) ) ); ?></strong></td>
						<td class="num"><strong><?php echo esc_html( number_format_i18n( array_sum( array_column( $forwp_social_vrows, 'subscribersGained' ) ) ) ); ?></strong></td>
					</tr>
					<?php foreach ( $forwp_social_vrows as $forwp_social_v ) : ?>
						<?php
						$forwp_social_vid   = (string) ( $forwp_social_v['id'] ?? '' );
						$forwp_social_views = (float) ( $forwp_social_v['views'] ?? 0 );
						?>
						<tr>
							<td class="forwp-social-an__video">
								<?php if ( ! empty( $forwp_social_v['thumbnail'] ) ) : ?>
									<span class="forwp-social-an__thumb">
										<img src="<?php echo esc_url( (string) $forwp_social_v['thumbnail'] ); ?>" alt="" loading="lazy" width="64" height="36" />
										<?php if ( ! empty( $forwp_social_v['duration'] ) ) : ?>
											<em><?php echo esc_html( $forwp_social_mmss( $forwp_social_v['duration'] ) ); ?></em>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . rawurlencode( $forwp_social_vid ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== (string) ( $forwp_social_v['title'] ?? '' ) ? (string) $forwp_social_v['title'] : $forwp_social_vid ); ?></a>
							</td>
							<td><?php echo esc_html( ! empty( $forwp_social_v['published_at'] ) ? wp_date( get_option( 'date_format' ), strtotime( (string) $forwp_social_v['published_at'] ) ) : '—' ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $forwp_social_views ) ); ?> <span class="forwp-social-muted"><?php echo esc_html( number_format_i18n( 100 * $forwp_social_views / $forwp_social_vtotal, 1 ) . '%' ); ?></span></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) ( $forwp_social_v['estimatedMinutesWatched'] ?? 0 ) / 60, 1 ) ); ?></td>
							<td class="num"><?php echo esc_html( $forwp_social_mmss( $forwp_social_v['averageViewDuration'] ?? 0 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) ( $forwp_social_v['likes'] ?? 0 ) ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) ( $forwp_social_v['comments'] ?? 0 ) ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) ( $forwp_social_v['subscribersGained'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
