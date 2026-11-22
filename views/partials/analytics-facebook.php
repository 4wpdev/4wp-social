<?php
/**
 * Facebook panels: Page, 28-day insights, views by day, Reels table.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { page, insights, reels }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_num  = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};
$forwp_social_mmss = static function ( $sec ): string {
	$sec = (int) round( (float) $sec );
	return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
};

$forwp_social_fb_page = is_array( $forwp_social_data['page'] ?? null ) ? $forwp_social_data['page'] : array();

// Insights by day: name => [ Y-m-d => value ]; totals = sum over the period.
$forwp_social_fb_series = array();
foreach ( (array) ( $forwp_social_data['insights']['data'] ?? array() ) as $forwp_social_m ) {
	$forwp_social_name = (string) ( $forwp_social_m['name'] ?? '' );
	foreach ( (array) ( $forwp_social_m['values'] ?? array() ) as $forwp_social_v ) {
		$forwp_social_day = substr( (string) ( $forwp_social_v['end_time'] ?? '' ), 0, 10 );
		if ( '' !== $forwp_social_day && is_numeric( $forwp_social_v['value'] ?? null ) ) {
			$forwp_social_fb_series[ $forwp_social_name ][ $forwp_social_day ] = (float) $forwp_social_v['value'];
		}
	}
}
$forwp_social_fb_total = static function ( string $name ) use ( $forwp_social_fb_series ) {
	return isset( $forwp_social_fb_series[ $name ] ) ? array_sum( $forwp_social_fb_series[ $name ] ) : null;
};
$forwp_social_fb_daily = $forwp_social_fb_series['page_media_view'] ?? array();

$forwp_social_fb_reels = (array) ( $forwp_social_data['reels']['data'] ?? array() );
?>
<div class="forwp-social-an">
	<?php if ( isset( $forwp_social_fb_page['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_fb_page['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title">
			<?php
			echo esc_html__( 'Page', '4wp-social' );
			if ( ! empty( $forwp_social_fb_page['name'] ) ) {
				echo ' · ';
				printf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( (string) ( $forwp_social_fb_page['link'] ?? '' ) ), esc_html( (string) $forwp_social_fb_page['name'] ) );
			}
			?>
		</h3>
		<div class="forwp-social-an__tiles">
			<?php
			foreach ( array(
				__( 'Followers', '4wp-social' ) => $forwp_social_fb_page['followers_count'] ?? null,
				__( 'Likes', '4wp-social' )     => $forwp_social_fb_page['fan_count'] ?? null,
				__( 'Reels', '4wp-social' )     => count( $forwp_social_fb_reels ),
			) as $forwp_social_label => $forwp_social_value ) :
				?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_value ) ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_data['insights']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['insights']['error'] ); ?></p>
	<?php elseif ( ! $forwp_social_fb_series ) : ?>
		<p class="forwp-social-muted"><?php esc_html_e( 'Page insights: no data. Meta returns them only with the read_insights permission (add it to the Facebook Login configuration and reconnect).', '4wp-social' ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Last 28 days', '4wp-social' ); ?></h3>
		<div class="forwp-social-an__tiles">
			<?php
			foreach ( array(
				__( 'Views', '4wp-social' )          => $forwp_social_fb_total( 'page_media_view' ),
				__( 'Engagements', '4wp-social' )    => $forwp_social_fb_total( 'page_post_engagements' ),
				__( 'New follows', '4wp-social' )    => $forwp_social_fb_total( 'page_follows' ),
				__( 'Unique follows', '4wp-social' ) => $forwp_social_fb_total( 'page_daily_follows_unique' ),
			) as $forwp_social_label => $forwp_social_value ) :
				?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_value ) ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $forwp_social_fb_daily ) : ?>
		<?php
		$forwp_social_days = array_keys( $forwp_social_fb_daily );
		$forwp_social_max  = max( 1, max( $forwp_social_fb_daily ) );
		$forwp_social_w    = 100 / count( $forwp_social_fb_daily );
		?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Views by day', '4wp-social' ); ?></h3>
		<svg class="forwp-social-an__chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Views by day', '4wp-social' ); ?>">
			<?php
			$forwp_social_i = 0;
			foreach ( $forwp_social_fb_daily as $forwp_social_day => $forwp_social_value ) :
				$forwp_social_h = 38 * ( $forwp_social_value / $forwp_social_max );
				?>
				<rect x="<?php echo esc_attr( (string) ( $forwp_social_i * $forwp_social_w + $forwp_social_w * 0.15 ) ); ?>" y="<?php echo esc_attr( (string) ( 40 - $forwp_social_h ) ); ?>" width="<?php echo esc_attr( (string) ( $forwp_social_w * 0.7 ) ); ?>" height="<?php echo esc_attr( (string) $forwp_social_h ); ?>"><title><?php echo esc_html( $forwp_social_day . ': ' . number_format_i18n( $forwp_social_value ) ); ?></title></rect>
				<?php
				++$forwp_social_i;
			endforeach;
			?>
		</svg>
		<div class="forwp-social-an__axis"><span><?php echo esc_html( (string) reset( $forwp_social_days ) ); ?></span><span><?php echo esc_html( 'max ' . number_format_i18n( $forwp_social_max ) ); ?></span><span><?php echo esc_html( (string) end( $forwp_social_days ) ); ?></span></div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_data['reels']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['reels']['error'] ); ?></p>
	<?php elseif ( $forwp_social_fb_reels ) : ?>
		<?php
		// key => [ label, source: 'f' field | 'i' insight ].
		$forwp_social_cols = array(
			'views'                   => array( __( 'Views', '4wp-social' ), 'f' ),
			'post_impressions_unique' => array( __( 'Reach', '4wp-social' ), 'i' ),
			'likes'                   => array( __( 'Likes', '4wp-social' ), 'f' ),
			'comments'                => array( __( 'Comments', '4wp-social' ), 'f' ),
		);
		$forwp_social_val  = static function ( array $reel, string $key, string $src ) {
			$value = 'i' === $src ? ( $reel['insights'][ $key ] ?? null ) : ( $reel[ $key ] ?? null );
			return is_numeric( $value ) ? (float) $value : null;
		};
		$forwp_social_sum  = static function ( string $key, string $src ) use ( $forwp_social_fb_reels, $forwp_social_val ) {
			$total = null;
			foreach ( $forwp_social_fb_reels as $forwp_social_r ) {
				$value = $forwp_social_val( (array) $forwp_social_r, $key, $src );
				if ( null !== $value ) {
					$total = (float) $total + $value;
				}
			}
			return $total;
		};
		?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: %d: number of reels. */
			echo esc_html( sprintf( __( 'Reels (%d) · lifetime numbers', '4wp-social' ), count( $forwp_social_fb_reels ) ) );
			?>
		</h3>
		<?php if ( ! empty( $forwp_social_data['reels']['insights_error'] ) ) : ?>
			<p class="forwp-social-muted"><?php echo esc_html( __( 'Reach and watch time:', '4wp-social' ) . ' ' . (string) $forwp_social_data['reels']['insights_error'] ); ?></p>
		<?php endif; ?>
		<div class="forwp-social-an__table-wrap">
			<table class="widefat striped forwp-social-an__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reel', '4wp-social' ); ?></th>
						<th><?php esc_html_e( 'Published', '4wp-social' ); ?></th>
						<?php foreach ( $forwp_social_cols as $forwp_social_col ) : ?>
							<th class="num"><?php echo esc_html( $forwp_social_col[0] ); ?></th>
						<?php endforeach; ?>
						<th class="num"><?php esc_html_e( 'Avg watch', '4wp-social' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="forwp-social-an__total">
						<td><strong><?php esc_html_e( 'Total', '4wp-social' ); ?></strong></td>
						<td></td>
						<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_col ) : ?>
							<td class="num"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_sum( $forwp_social_key, $forwp_social_col[1] ) ) ); ?></strong></td>
						<?php endforeach; ?>
						<td></td>
					</tr>
					<?php foreach ( $forwp_social_fb_reels as $forwp_social_r ) : ?>
						<?php
						$forwp_social_r       = (array) $forwp_social_r;
						$forwp_social_caption = trim( (string) strtok( html_entity_decode( (string) ( $forwp_social_r['description'] ?? '' ), ENT_QUOTES, 'UTF-8' ), "\n" ) );
						$forwp_social_link    = (string) ( $forwp_social_r['permalink_url'] ?? '' );
						$forwp_social_link    = '' !== $forwp_social_link && '/' === $forwp_social_link[0] ? 'https://www.facebook.com' . $forwp_social_link : $forwp_social_link;
						$forwp_social_avg     = $forwp_social_r['insights']['post_video_avg_time_watched'] ?? null;
						?>
						<tr>
							<td class="forwp-social-an__video">
								<?php if ( ! empty( $forwp_social_r['picture'] ) ) : ?>
									<span class="forwp-social-an__thumb forwp-social-an__thumb--square">
										<img src="<?php echo esc_url( (string) $forwp_social_r['picture'] ); ?>" alt="" loading="lazy" width="36" height="36" />
										<?php if ( ! empty( $forwp_social_r['length'] ) ) : ?>
											<em><?php echo esc_html( $forwp_social_mmss( $forwp_social_r['length'] ) ); ?></em>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<span><a href="<?php echo esc_url( $forwp_social_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $forwp_social_caption ? wp_trim_words( $forwp_social_caption, 14 ) : __( '(no description)', '4wp-social' ) ); ?></a></span>
							</td>
							<td><?php echo esc_html( ! empty( $forwp_social_r['created_time'] ) ? wp_date( get_option( 'date_format' ), strtotime( (string) $forwp_social_r['created_time'] ) ) : '—' ); ?></td>
							<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_col ) : ?>
								<td class="num"><?php echo esc_html( $forwp_social_num( $forwp_social_val( $forwp_social_r, $forwp_social_key, $forwp_social_col[1] ) ) ); ?></td>
							<?php endforeach; ?>
							<td class="num"><?php echo esc_html( is_numeric( $forwp_social_avg ) ? $forwp_social_mmss( (float) $forwp_social_avg / 1000 ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
