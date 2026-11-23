<?php
/**
 * Google Analytics: traffic sources of the chosen period (shown under the shared panel).
 *
 * @package ForWP\Social
 *
 * @var ForWP\Social\Contracts\Analytics_Source_Interface $forwp_social_source Source.
 * @var array{preset: string, start: string, end: string} $forwp_social_range  Period.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $forwp_social_source instanceof ForWP\Social\Modules\Analytics\Sources\Google_Analytics_Source || ForWP\Social\Modules\Analytics\Analytics::READY !== $forwp_social_status ) {
	return;
}

if ( ! empty( $forwp_social_data['metrics_error'] ) ) {
	echo '<p class="forwp-social-muted">' . esc_html( (string) $forwp_social_data['metrics_error'] ) . '</p>';
}

$forwp_social_traffic = $forwp_social_source->traffic( $forwp_social_range['start'], $forwp_social_range['end'] );

ob_start();
?>
<div class="forwp-social-an">
	<?php if ( is_wp_error( $forwp_social_traffic ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( $forwp_social_traffic->get_error_message() ); ?></p>
	<?php else : ?>
		<?php
		foreach ( array(
			'channels' => __( 'Traffic channels', '4wp-social' ),
			'sources'  => __( 'Top sources', '4wp-social' ),
		) as $forwp_social_part => $forwp_social_title ) :
			$forwp_social_rows = (array) ( $forwp_social_traffic[ $forwp_social_part ] ?? array() );
			$forwp_social_all  = max( 1, (float) array_sum( array_column( $forwp_social_rows, 'sessions' ) ) );
			?>
			<h3 class="forwp-social-an__title"><?php echo esc_html( $forwp_social_title . ' · ' . $forwp_social_range['start'] . ' → ' . $forwp_social_range['end'] ); ?></h3>
			<?php if ( ! $forwp_social_rows ) : ?>
				<p class="forwp-social-muted"><?php esc_html_e( 'No data for this period.', '4wp-social' ); ?></p>
			<?php else : ?>
				<div class="forwp-social-an__table-wrap">
					<table class="widefat striped forwp-social-an__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source', '4wp-social' ); ?></th>
								<th class="num"><?php esc_html_e( 'Sessions', '4wp-social' ); ?></th>
								<th class="num"><?php esc_html_e( 'Users', '4wp-social' ); ?></th>
								<th class="num"><?php esc_html_e( '% of sessions', '4wp-social' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $forwp_social_rows as $forwp_social_row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $forwp_social_row['name'] ); ?></td>
									<td class="num"><?php echo esc_html( number_format_i18n( (float) $forwp_social_row['sessions'] ) ); ?></td>
									<td class="num"><?php echo esc_html( number_format_i18n( (float) $forwp_social_row['users'] ) ); ?></td>
									<td class="num"><?php echo esc_html( number_format_i18n( 100 * (float) $forwp_social_row['sessions'] / $forwp_social_all, 1 ) . '%' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
$forwp_social_tail = (string) ob_get_clean();
