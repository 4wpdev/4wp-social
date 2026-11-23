<?php
/**
 * TikTok: account tiles (go into the shared panel when connected) and lifetime videos table (shown under the panel, folded).
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { user, videos }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_num  = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};
$forwp_social_mmss = static function ( $sec ): string {
	$sec = (int) round( (float) $sec );
	return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
};

$forwp_social_tt_user   = is_array( $forwp_social_data['user'] ?? null ) ? $forwp_social_data['user'] : array();
$forwp_social_tt_user   = isset( $forwp_social_tt_user['user'] ) ? (array) $forwp_social_tt_user['user'] + $forwp_social_tt_user : $forwp_social_tt_user;
$forwp_social_tt_videos = (array) ( $forwp_social_data['videos']['videos'] ?? array() );

// Connected: account tiles go into the shared panel's first row, the lifetime table under the panel.
$forwp_social_in_panel = ForWP\Social\Modules\Analytics\Analytics::READY === $forwp_social_status;
if ( $forwp_social_in_panel && ! isset( $forwp_social_tt_user['error'] ) ) {
	$forwp_social_handle = (string) ( $forwp_social_tt_user['username'] ?? ( $forwp_social_tt_user['display_name'] ?? '' ) );
	$forwp_social_lead   = array(
		/* translators: %s: account name. */
		'title' => '' !== $forwp_social_handle ? sprintf( __( 'Account @%s · all time', '4wp-social' ), $forwp_social_handle ) : __( 'Account · all time', '4wp-social' ),
		'tiles' => array(
			__( 'Followers', '4wp-social' ) => $forwp_social_num( $forwp_social_tt_user['follower_count'] ?? null ),
			__( 'Likes', '4wp-social' )     => $forwp_social_num( $forwp_social_tt_user['likes_count'] ?? null ),
			__( 'Videos', '4wp-social' )    => $forwp_social_num( $forwp_social_tt_user['video_count'] ?? null ),
		),
	);
}
if ( $forwp_social_in_panel ) {
	ob_start();
	echo '<details class="forwp-social-json"><summary>' . esc_html__( 'Videos · lifetime numbers', '4wp-social' ) . '</summary>';
}
?>
<div class="forwp-social-an">
	<?php if ( $forwp_social_in_panel && isset( $forwp_social_lead ) ) : ?>
		<?php // Account tiles are rendered by the panel. ?>
	<?php elseif ( isset( $forwp_social_tt_user['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_tt_user['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title">
			<?php
			echo esc_html__( 'Account', '4wp-social' );
			$forwp_social_name = (string) ( $forwp_social_tt_user['username'] ?? '' );
			if ( '' !== $forwp_social_name ) {
				echo ' · ';
				printf( '<a href="%s" target="_blank" rel="noopener noreferrer">@%s</a>', esc_url( 'https://www.tiktok.com/@' . rawurlencode( $forwp_social_name ) ), esc_html( $forwp_social_name ) );
			} elseif ( ! empty( $forwp_social_tt_user['display_name'] ) ) {
				echo ' · ' . esc_html( (string) $forwp_social_tt_user['display_name'] );
			}
			?>
		</h3>
		<div class="forwp-social-an__tiles">
			<?php
			foreach ( array(
				__( 'Followers', '4wp-social' ) => $forwp_social_tt_user['follower_count'] ?? null,
				__( 'Following', '4wp-social' ) => $forwp_social_tt_user['following_count'] ?? null,
				__( 'Likes', '4wp-social' )     => $forwp_social_tt_user['likes_count'] ?? null,
				__( 'Videos', '4wp-social' )    => $forwp_social_tt_user['video_count'] ?? null,
			) as $forwp_social_label => $forwp_social_value ) :
				?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_value ) ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $forwp_social_data['videos']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['videos']['error'] ); ?></p>
	<?php elseif ( $forwp_social_tt_videos ) : ?>
		<?php
		$forwp_social_cols = array(
			'view_count'    => __( 'Views', '4wp-social' ),
			'like_count'    => __( 'Likes', '4wp-social' ),
			'comment_count' => __( 'Comments', '4wp-social' ),
			'share_count'   => __( 'Shares', '4wp-social' ),
		);
		$forwp_social_sum  = static function ( string $key ) use ( $forwp_social_tt_videos ): float {
			return (float) array_sum( array_map( static fn( $v ) => (float) ( ( (array) $v )[ $key ] ?? 0 ), $forwp_social_tt_videos ) );
		};
		$forwp_social_all  = max( 1, $forwp_social_sum( 'view_count' ) );
	?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: %d: number of videos. */
			echo esc_html( sprintf( __( 'Videos (%d) · lifetime numbers', '4wp-social' ), count( $forwp_social_tt_videos ) ) );
			?>
		</h3>
		<?php if ( ! empty( $forwp_social_data['videos']['error_more'] ) ) : ?>
			<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['videos']['error_more'] ); ?></p>
		<?php endif; ?>
		<div class="forwp-social-an__table-wrap">
			<table class="widefat striped forwp-social-an__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Video', '4wp-social' ); ?></th>
						<th><?php esc_html_e( 'Published', '4wp-social' ); ?></th>
						<?php foreach ( $forwp_social_cols as $forwp_social_label ) : ?>
							<th class="num"><?php echo esc_html( $forwp_social_label ); ?></th>
						<?php endforeach; ?>
						<th class="num"><?php esc_html_e( '% of views', '4wp-social' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="forwp-social-an__total">
						<td><strong><?php esc_html_e( 'Total', '4wp-social' ); ?></strong></td>
						<td></td>
						<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_label ) : ?>
							<td class="num"><strong><?php echo esc_html( $forwp_social_num( $forwp_social_sum( $forwp_social_key ) ) ); ?></strong></td>
						<?php endforeach; ?>
						<td class="num"><strong>100%</strong></td>
					</tr>
					<?php foreach ( $forwp_social_tt_videos as $forwp_social_v ) : ?>
						<?php
						$forwp_social_v     = (array) $forwp_social_v;
						$forwp_social_title = trim( (string) ( $forwp_social_v['title'] ?? '' ) );
						$forwp_social_title = '' !== $forwp_social_title ? $forwp_social_title : trim( (string) strtok( (string) ( $forwp_social_v['video_description'] ?? '' ), "\n" ) );
						?>
						<tr>
							<td class="forwp-social-an__video">
								<?php if ( ! empty( $forwp_social_v['cover_image_url'] ) ) : ?>
									<span class="forwp-social-an__thumb forwp-social-an__thumb--square">
										<img src="<?php echo esc_url( (string) $forwp_social_v['cover_image_url'] ); ?>" alt="" loading="lazy" width="36" height="36" />
										<?php if ( ! empty( $forwp_social_v['duration'] ) ) : ?>
											<em><?php echo esc_html( $forwp_social_mmss( $forwp_social_v['duration'] ) ); ?></em>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<span><a href="<?php echo esc_url( (string) ( $forwp_social_v['share_url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $forwp_social_title ? wp_trim_words( $forwp_social_title, 14 ) : __( '(no title)', '4wp-social' ) ); ?></a></span>
							</td>
							<td><?php echo esc_html( ! empty( $forwp_social_v['create_time'] ) ? wp_date( get_option( 'date_format' ), (int) $forwp_social_v['create_time'] ) : '—' ); ?></td>
							<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_label ) : ?>
								<td class="num"><?php echo esc_html( $forwp_social_num( $forwp_social_v[ $forwp_social_key ] ?? null ) ); ?></td>
							<?php endforeach; ?>
							<td class="num"><?php echo esc_html( number_format_i18n( 100 * (float) ( $forwp_social_v['view_count'] ?? 0 ) / $forwp_social_all, 1 ) . '%' ); ?></td>
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
