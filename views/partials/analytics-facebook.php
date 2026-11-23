<?php
/**
 * Facebook: Page tiles (go into the shared panel when connected) and lifetime Reels table (shown under the panel, folded).
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { page, reels }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_num  = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};
$forwp_social_mmss = static function ( $sec ): string {
	$sec = (int) round( (float) $sec );
	return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
};

$forwp_social_fb_page  = is_array( $forwp_social_data['page'] ?? null ) ? $forwp_social_data['page'] : array();
$forwp_social_fb_reels = (array) ( $forwp_social_data['reels']['data'] ?? array() );

// Connected: Page tiles go into the shared panel's first row, the lifetime table under the panel.
$forwp_social_in_panel = ForWP\Social\Modules\Analytics\Analytics::READY === $forwp_social_status;
if ( $forwp_social_in_panel && ! isset( $forwp_social_fb_page['error'] ) ) {
	$forwp_social_name = (string) ( $forwp_social_fb_page['name'] ?? '' );
	$forwp_social_lead = array(
		/* translators: %s: Page name. */
		'title' => '' !== $forwp_social_name ? sprintf( __( 'Page %s · all time', '4wp-social' ), $forwp_social_name ) : __( 'Page · all time', '4wp-social' ),
		'tiles' => array(
			__( 'Followers', '4wp-social' ) => $forwp_social_num( $forwp_social_fb_page['followers_count'] ?? null ),
			__( 'Likes', '4wp-social' )     => $forwp_social_num( $forwp_social_fb_page['fan_count'] ?? null ),
			__( 'Reels', '4wp-social' )     => $forwp_social_num( count( $forwp_social_fb_reels ) ),
		),
	);
}
if ( $forwp_social_in_panel ) {
	if ( ! empty( $forwp_social_data['metrics_error'] ) ) {
		echo '<p class="forwp-social-muted">' . esc_html( (string) $forwp_social_data['metrics_error'] ) . '</p>';
	} elseif ( ! empty( $forwp_social_data['reels']['insights_error'] ) ) {
		echo '<p class="forwp-social-muted">' . esc_html__( 'Page insights by day need the read_insights permission: add it to the Facebook Login configuration and reconnect.', '4wp-social' ) . '</p>';
	}
	ob_start();
	echo '<details class="forwp-social-json"><summary>' . esc_html__( 'Reels · lifetime numbers', '4wp-social' ) . '</summary>';
}
?>
<div class="forwp-social-an">
	<?php if ( $forwp_social_in_panel && isset( $forwp_social_lead ) ) : ?>
		<?php // Page tiles are rendered by the panel. ?>
	<?php elseif ( isset( $forwp_social_fb_page['error'] ) ) : ?>
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
<?php
if ( $forwp_social_in_panel ) {
	echo '</details>';
	$forwp_social_tail = (string) ob_get_clean();
}
