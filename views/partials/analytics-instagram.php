<?php
/**
 * Instagram panels: account, 28-day insights, reach by day, posts table.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { account, insights, daily, media }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_num = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};
$forwp_social_mmss = static function ( $sec ): string {
	$sec = (int) round( (float) $sec );
	return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
};

$forwp_social_ig_account  = is_array( $forwp_social_data['account'] ?? null ) ? $forwp_social_data['account'] : array();
$forwp_social_ig_insights = array();
foreach ( (array) ( $forwp_social_data['insights']['data'] ?? array() ) as $forwp_social_m ) {
	$forwp_social_ig_insights[ (string) ( $forwp_social_m['name'] ?? '' ) ] = $forwp_social_m['total_value']['value'] ?? null;
}

// Reach by day: [ Y-m-d => value ].
$forwp_social_ig_daily = array();
foreach ( (array) ( $forwp_social_data['daily']['data'][0]['values'] ?? array() ) as $forwp_social_v ) {
	$forwp_social_day = substr( (string) ( $forwp_social_v['end_time'] ?? '' ), 0, 10 );
	if ( '' !== $forwp_social_day ) {
		$forwp_social_ig_daily[ $forwp_social_day ] = (float) ( $forwp_social_v['value'] ?? 0 );
	}
}

$forwp_social_ig_media = (array) ( $forwp_social_data['media']['data'] ?? array() );
?>
<div class="forwp-social-an">
	<?php if ( isset( $forwp_social_ig_account['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_ig_account['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title">
			<?php
			echo esc_html__( 'Account', '4wp-social' );
			if ( ! empty( $forwp_social_ig_account['username'] ) ) {
				echo ' · ';
				printf( '<a href="%s" target="_blank" rel="noopener noreferrer">@%s</a>', esc_url( 'https://www.instagram.com/' . rawurlencode( (string) $forwp_social_ig_account['username'] ) . '/' ), esc_html( (string) $forwp_social_ig_account['username'] ) );
			}
			?>
		</h3>
		<div class="forwp-social-an__tiles">
			<?php
			foreach ( array(
				__( 'Followers', '4wp-social' ) => $forwp_social_ig_account['followers_count'] ?? null,
				__( 'Following', '4wp-social' ) => $forwp_social_ig_account['follows_count'] ?? null,
				__( 'Posts', '4wp-social' )     => $forwp_social_ig_account['media_count'] ?? null,
			) as $forwp_social_label => $forwp_social_value ) :
				?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_value ) ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_data['insights']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['insights']['error'] ); ?></p>
	<?php elseif ( $forwp_social_ig_insights ) : ?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Last 28 days', '4wp-social' ); ?></h3>
		<div class="forwp-social-an__tiles">
			<?php
			foreach ( array(
				__( 'Views', '4wp-social' )            => $forwp_social_ig_insights['views'] ?? null,
				__( 'Accounts reached', '4wp-social' ) => $forwp_social_ig_insights['reach'] ?? null,
				__( 'Accounts engaged', '4wp-social' ) => $forwp_social_ig_insights['accounts_engaged'] ?? null,
				__( 'Interactions', '4wp-social' )     => $forwp_social_ig_insights['total_interactions'] ?? null,
			) as $forwp_social_label => $forwp_social_value ) :
				?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_value ) ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $forwp_social_ig_daily ) : ?>
		<?php
		$forwp_social_days = array_keys( $forwp_social_ig_daily );
		$forwp_social_max  = max( 1, max( $forwp_social_ig_daily ) );
		$forwp_social_w    = 100 / count( $forwp_social_ig_daily );
		?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Accounts reached by day', '4wp-social' ); ?></h3>
		<svg class="forwp-social-an__chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Accounts reached by day', '4wp-social' ); ?>">
			<?php
			$forwp_social_i = 0;
			foreach ( $forwp_social_ig_daily as $forwp_social_day => $forwp_social_value ) :
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

	<?php if ( isset( $forwp_social_data['media']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['media']['error'] ); ?></p>
	<?php elseif ( $forwp_social_ig_media ) : ?>
		<?php
		$forwp_social_sum = static function ( string $key, bool $insight = true ) use ( $forwp_social_ig_media ): float {
			$total = 0.0;
			foreach ( $forwp_social_ig_media as $forwp_social_p ) {
				$total += (float) ( $insight ? ( $forwp_social_p['insights'][ $key ] ?? 0 ) : ( $forwp_social_p[ $key ] ?? 0 ) );
			}
			return $total;
		};
		$forwp_social_cols = array(
			'views'                   => array( __( 'Views', '4wp-social' ), true ),
			'reach'                   => array( __( 'Reach', '4wp-social' ), true ),
			'like_count'              => array( __( 'Likes', '4wp-social' ), false ),
			'comments_count'          => array( __( 'Comments', '4wp-social' ), false ),
			'saved'                   => array( __( 'Saved', '4wp-social' ), true ),
			'shares'                  => array( __( 'Shares', '4wp-social' ), true ),
		);
		?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: %d: number of posts. */
			echo esc_html( sprintf( __( 'Posts (%d) · lifetime numbers', '4wp-social' ), count( $forwp_social_ig_media ) ) );
			?>
		</h3>
		<div class="forwp-social-an__table-wrap">
			<table class="widefat striped forwp-social-an__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', '4wp-social' ); ?></th>
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
					<?php foreach ( $forwp_social_ig_media as $forwp_social_p ) : ?>
						<?php
						$forwp_social_thumb   = (string) ( $forwp_social_p['thumbnail_url'] ?? ( 'IMAGE' === ( $forwp_social_p['media_type'] ?? '' ) || 'CAROUSEL_ALBUM' === ( $forwp_social_p['media_type'] ?? '' ) ? ( $forwp_social_p['media_url'] ?? '' ) : '' ) );
						$forwp_social_caption = trim( (string) strtok( html_entity_decode( (string) ( $forwp_social_p['caption'] ?? '' ), ENT_QUOTES, 'UTF-8' ), "\n" ) );
						$forwp_social_avg     = $forwp_social_p['insights']['ig_reels_avg_watch_time'] ?? null;
						?>
						<tr>
							<td class="forwp-social-an__video">
								<?php if ( '' !== $forwp_social_thumb ) : ?>
									<span class="forwp-social-an__thumb forwp-social-an__thumb--square"><img src="<?php echo esc_url( $forwp_social_thumb ); ?>" alt="" loading="lazy" width="36" height="36" /></span>
								<?php endif; ?>
								<span>
									<a href="<?php echo esc_url( (string) ( $forwp_social_p['permalink'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $forwp_social_caption ? wp_trim_words( $forwp_social_caption, 14 ) : __( '(no caption)', '4wp-social' ) ); ?></a>
									<br /><span class="forwp-social-muted"><?php echo esc_html( strtolower( (string) ( $forwp_social_p['media_product_type'] ?? $forwp_social_p['media_type'] ?? '' ) ) ); ?></span>
									<?php if ( ! empty( $forwp_social_p['insights_error'] ) ) : ?>
										<br /><span class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_p['insights_error'] ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td><?php echo esc_html( ! empty( $forwp_social_p['timestamp'] ) ? wp_date( get_option( 'date_format' ), strtotime( (string) $forwp_social_p['timestamp'] ) ) : '—' ); ?></td>
							<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_col ) : ?>
								<td class="num"><?php echo esc_html( $forwp_social_num( $forwp_social_col[1] ? ( $forwp_social_p['insights'][ $forwp_social_key ] ?? null ) : ( $forwp_social_p[ $forwp_social_key ] ?? null ) ) ); ?></td>
							<?php endforeach; ?>
							<td class="num"><?php echo esc_html( null !== $forwp_social_avg ? $forwp_social_mmss( (float) $forwp_social_avg / 1000 ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
