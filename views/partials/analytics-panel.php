<?php
/**
 * Shared analytics panel for any Analytics_Daily_Interface source:
 * period tiles with change vs the previous period, daily chart (tooltip, top items of a day), sortable items table.
 *
 * @package ForWP\Social
 *
 * @var string                                                 $forwp_social_slug   Source slug.
 * @var ForWP\Social\Contracts\Analytics_Daily_Interface        $forwp_social_source Source.
 * @var array{preset: string, start: string, end: string}       $forwp_social_range  Period.
 * @var array{title: string, tiles: array<string, string>}|null $forwp_social_lead   Account tiles (all time), shown in the first row.
 */

use ForWP\Social\Modules\Analytics\Metrics_Store;
use ForWP\Social\Modules\Posting\Status_Index;

defined( 'ABSPATH' ) || exit;

$forwp_social_p_metrics = $forwp_social_source->metrics();
$forwp_social_p_rows    = Metrics_Store::rows( $forwp_social_slug, $forwp_social_range['start'], $forwp_social_range['end'] );
$forwp_social_p_prev_r  = Metrics_Store::previous_range( $forwp_social_range['start'], $forwp_social_range['end'] );
$forwp_social_p_prev    = Metrics_Store::rows( $forwp_social_slug, $forwp_social_p_prev_r[0], $forwp_social_p_prev_r[1] );
$forwp_social_p_tot     = Metrics_Store::totals( $forwp_social_p_rows, $forwp_social_p_metrics );
$forwp_social_p_tot_old = Metrics_Store::totals( $forwp_social_p_prev, $forwp_social_p_metrics );

$forwp_social_fmt = static function ( $value, string $format ): string {
	if ( null === $value ) {
		return '—';
	}
	$value = (float) $value;
	if ( 'time' === $format ) {
		$sec = (int) round( $value );
		return $sec >= 3600 ? gmdate( 'G:i:s', $sec ) : gmdate( 'i:s', $sec );
	}
	if ( 'hours' === $format ) {
		return number_format_i18n( $value / 60, 1 );
	}
	return number_format_i18n( $value );
};

$forwp_social_chart_key = (string) array_key_first( $forwp_social_p_metrics );
foreach ( $forwp_social_p_metrics as $forwp_social_key => $forwp_social_def ) {
	if ( ! empty( $forwp_social_def['chart'] ) ) {
		$forwp_social_chart_key = $forwp_social_key;
		break;
	}
}
?>
<div class="forwp-social-an forwp-social-an-panel" data-source="<?php echo esc_attr( $forwp_social_slug ); ?>">
	<?php
	$forwp_social_period_title = sprintf(
		/* translators: 1: first day, 2: last day, 3: previous first day, 4: previous last day. */
		__( 'Period %1$s → %2$s · vs %3$s → %4$s', '4wp-social' ),
		$forwp_social_range['start'],
		$forwp_social_range['end'],
		$forwp_social_p_prev_r[0],
		$forwp_social_p_prev_r[1]
	);
	?>
	<div class="forwp-social-an__groups">
		<?php if ( ! empty( $forwp_social_lead ) ) : ?>
			<div class="forwp-social-an__group">
				<h3 class="forwp-social-an__title"><?php echo esc_html( (string) $forwp_social_lead['title'] ); ?></h3>
				<div class="forwp-social-an__tiles">
					<?php foreach ( (array) $forwp_social_lead['tiles'] as $forwp_social_label => $forwp_social_value ) : ?>
						<div class="forwp-social-an__tile"><strong><?php echo esc_html( (string) $forwp_social_value ); ?></strong><span><?php echo esc_html( (string) $forwp_social_label ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<div class="forwp-social-an__group forwp-social-an__group--wide">
			<h3 class="forwp-social-an__title"><?php echo esc_html( $forwp_social_period_title ); ?></h3>
			<?php if ( ! $forwp_social_p_rows ) : ?>
				<p class="forwp-social-muted"><?php esc_html_e( 'No daily data for this period yet. Click "Fetch now" to load it.', '4wp-social' ); ?></p>
			<?php else : ?>
				<div class="forwp-social-an__tiles">
					<?php foreach ( $forwp_social_p_metrics as $forwp_social_key => $forwp_social_def ) : ?>
						<?php $forwp_social_delta = Metrics_Store::delta( $forwp_social_p_tot[ $forwp_social_key ], $forwp_social_p_tot_old[ $forwp_social_key ] ); ?>
						<div class="forwp-social-an__tile">
							<strong><?php echo esc_html( $forwp_social_fmt( $forwp_social_p_tot[ $forwp_social_key ], (string) ( $forwp_social_def['format'] ?? 'num' ) ) ); ?></strong>
							<span><?php echo esc_html( (string) $forwp_social_def['label'] ); ?></span>
							<?php if ( null !== $forwp_social_delta ) : ?>
								<em class="forwp-social-an__delta <?php echo $forwp_social_delta >= 0 ? 'is-up' : 'is-down'; ?>" title="<?php echo esc_attr( $forwp_social_fmt( $forwp_social_p_tot_old[ $forwp_social_key ], (string) ( $forwp_social_def['format'] ?? 'num' ) ) ); ?>">
									<?php echo esc_html( ( $forwp_social_delta >= 0 ? '▲ ' : '▼ ' ) . number_format_i18n( abs( $forwp_social_delta ), 1 ) . '%' ); ?>
								</em>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $forwp_social_p_rows ) : ?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: %s: metric name, e.g. Views. */
			echo esc_html( sprintf( __( '%s by day', '4wp-social' ), (string) $forwp_social_p_metrics[ $forwp_social_chart_key ]['label'] ) );
			?>
			<span class="forwp-social-muted"><?php esc_html_e( '· click a day for its top 5', '4wp-social' ); ?></span>
		</h3>
		<?php
		$forwp_social_days = Metrics_Store::days( $forwp_social_range['start'], $forwp_social_range['end'] );
		$forwp_social_max  = 1.0;
		foreach ( $forwp_social_p_rows as $forwp_social_r ) {
			$forwp_social_max = max( $forwp_social_max, (float) ( $forwp_social_r[ $forwp_social_chart_key ] ?? 0 ) );
		}
		$forwp_social_w = 100 / max( 1, count( $forwp_social_days ) );
		?>
		<div class="forwp-social-an__chart-wrap">
			<svg class="forwp-social-an__chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php echo esc_attr( (string) $forwp_social_p_metrics[ $forwp_social_chart_key ]['label'] ); ?>">
				<?php foreach ( $forwp_social_days as $forwp_social_i => $forwp_social_day ) : ?>
					<?php
					$forwp_social_r     = $forwp_social_p_rows[ $forwp_social_day ] ?? array();
					$forwp_social_val   = (float) ( $forwp_social_r[ $forwp_social_chart_key ] ?? 0 );
					$forwp_social_h     = max( 0.3, 38 * $forwp_social_val / $forwp_social_max );
					$forwp_social_date  = wp_date( get_option( 'date_format' ), strtotime( $forwp_social_day . ' 12:00:00 UTC' ) );
					$forwp_social_tip   = array( $forwp_social_date );
					$forwp_social_stats = array();
					foreach ( $forwp_social_p_metrics as $forwp_social_key => $forwp_social_def ) {
						$forwp_social_cell    = $forwp_social_fmt( isset( $forwp_social_r[ $forwp_social_key ] ) ? $forwp_social_r[ $forwp_social_key ] : null, (string) ( $forwp_social_def['format'] ?? 'num' ) );
						$forwp_social_tip[]   = $forwp_social_def['label'] . ': ' . $forwp_social_cell;
						$forwp_social_stats[] = array( (string) $forwp_social_def['label'], $forwp_social_cell );
					}
					?>
					<rect x="<?php echo esc_attr( (string) ( $forwp_social_i * $forwp_social_w ) ); ?>" y="0" width="<?php echo esc_attr( (string) $forwp_social_w ); ?>" height="40" class="forwp-social-an__hit" data-day="<?php echo esc_attr( $forwp_social_day ); ?>" data-date="<?php echo esc_attr( $forwp_social_date ); ?>" data-stats="<?php echo esc_attr( (string) wp_json_encode( $forwp_social_stats ) ); ?>" data-tip="<?php echo esc_attr( implode( "\n", $forwp_social_tip ) ); ?>" />
					<rect x="<?php echo esc_attr( (string) ( $forwp_social_i * $forwp_social_w + $forwp_social_w * 0.15 ) ); ?>" y="<?php echo esc_attr( (string) ( 40 - $forwp_social_h ) ); ?>" width="<?php echo esc_attr( (string) ( $forwp_social_w * 0.7 ) ); ?>" height="<?php echo esc_attr( (string) $forwp_social_h ); ?>" class="forwp-social-an__bar" data-day="<?php echo esc_attr( $forwp_social_day ); ?>" />
				<?php endforeach; ?>
			</svg>
			<div class="forwp-social-an__tip" hidden></div>
		</div>
		<div class="forwp-social-an__axis"><span><?php echo esc_html( (string) reset( $forwp_social_days ) ); ?></span><span><?php echo esc_html( 'max ' . number_format_i18n( $forwp_social_max ) ); ?></span><span><?php echo esc_html( (string) end( $forwp_social_days ) ); ?></span></div>
		<div class="forwp-social-an__top" hidden></div>
	<?php endif; ?>

	<?php
	$forwp_social_items = Metrics_Store::items( $forwp_social_slug, $forwp_social_source, $forwp_social_range['start'], $forwp_social_range['end'], 200 );
	$forwp_social_cols  = $forwp_social_source->item_columns();
	?>
	<?php if ( is_wp_error( $forwp_social_items ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( $forwp_social_items->get_error_message() ); ?></p>
	<?php elseif ( $forwp_social_items ) : ?>
		<?php
		// Default order: newest published first.
		usort(
			$forwp_social_items,
			static function ( $a, $b ): int {
				return strcmp( (string) ( $b['published_at'] ?? '' ), (string) ( $a['published_at'] ?? '' ) );
			}
		);
		$forwp_social_first = (string) array_key_first( $forwp_social_cols );
		$forwp_social_posts = Status_Index::posts_by_remote( $forwp_social_slug );
		$forwp_social_total = max( 1, (float) array_sum( array_column( $forwp_social_items, $forwp_social_first ) ) );
		?>
		<h3 class="forwp-social-an__title">
			<?php
			/* translators: 1: number of items, 2: first day, 3: last day. */
			echo esc_html( sprintf( __( 'Items (%1$d) · %2$s → %3$s', '4wp-social' ), count( $forwp_social_items ), $forwp_social_range['start'], $forwp_social_range['end'] ) );
			?>
		</h3>
		<div class="forwp-social-an__table-wrap">
			<table class="widefat striped forwp-social-an__table" data-sortable>
				<thead>
					<tr>
						<th data-sort="text"><button type="button"><?php esc_html_e( 'Title', '4wp-social' ); ?></button></th>
						<th data-sort="num" aria-sort="descending" class="is-sorted"><button type="button"><?php esc_html_e( 'Published', '4wp-social' ); ?></button></th>
						<?php foreach ( $forwp_social_cols as $forwp_social_col ) : ?>
							<th class="num" data-sort="num"><button type="button"><?php echo esc_html( (string) $forwp_social_col['label'] ); ?></button></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody class="forwp-social-an__total">
					<tr>
						<td><strong><?php esc_html_e( 'Total', '4wp-social' ); ?></strong></td>
						<td></td>
						<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_col ) : ?>
							<td class="num"><strong><?php echo 'time' === $forwp_social_col['format'] ? '' : esc_html( $forwp_social_fmt( array_sum( array_column( $forwp_social_items, $forwp_social_key ) ), (string) $forwp_social_col['format'] ) ); ?></strong></td>
						<?php endforeach; ?>
					</tr>
				</tbody>
				<tbody class="forwp-social-an__rows">
					<?php foreach ( $forwp_social_items as $forwp_social_v ) : ?>
						<?php
						$forwp_social_title = '' !== (string) ( $forwp_social_v['title'] ?? '' ) ? (string) $forwp_social_v['title'] : (string) ( $forwp_social_v['id'] ?? '' );
						$forwp_social_pub   = ! empty( $forwp_social_v['published_at'] ) ? (int) strtotime( (string) $forwp_social_v['published_at'] ) : 0;
						?>
						<tr>
							<td class="forwp-social-an__video" data-v="<?php echo esc_attr( strtolower( $forwp_social_title ) ); ?>">
								<?php if ( ! empty( $forwp_social_v['thumbnail'] ) ) : ?>
									<span class="forwp-social-an__thumb">
										<img src="<?php echo esc_url( (string) $forwp_social_v['thumbnail'] ); ?>" alt="" loading="lazy" width="64" height="36" />
										<?php if ( ! empty( $forwp_social_v['duration'] ) ) : ?>
											<em><?php echo esc_html( $forwp_social_fmt( $forwp_social_v['duration'], 'time' ) ); ?></em>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<span class="forwp-social-an__names">
									<a href="<?php echo esc_url( (string) ( $forwp_social_v['url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $forwp_social_title ); ?></a>
									<?php
									$forwp_social_pid = $forwp_social_posts[ (string) ( $forwp_social_v['id'] ?? '' ) ] ?? 0;
									if ( $forwp_social_pid ) :
										$forwp_social_pto  = get_post_type_object( (string) get_post_type( $forwp_social_pid ) );
										$forwp_social_icon = $forwp_social_pto && is_string( $forwp_social_pto->menu_icon ) && 0 === strpos( $forwp_social_pto->menu_icon, 'dashicons-' ) ? $forwp_social_pto->menu_icon : 'dashicons-admin-post';
										?>
										<a class="forwp-social-an__site" href="<?php echo esc_url( (string) get_permalink( $forwp_social_pid ) ); ?>" target="_blank" rel="noopener noreferrer">
											<span class="dashicons <?php echo esc_attr( $forwp_social_icon ); ?>" title="<?php echo esc_attr( $forwp_social_pto ? (string) $forwp_social_pto->labels->singular_name : '' ); ?>" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php echo esc_html( $forwp_social_pto ? (string) $forwp_social_pto->labels->singular_name : '' ); ?>:</span>
											<?php echo esc_html( (string) wp_parse_url( (string) get_permalink( $forwp_social_pid ), PHP_URL_PATH ) ); ?>
										</a>
									<?php endif; ?>
								</span>
							</td>
							<td data-v="<?php echo esc_attr( (string) $forwp_social_pub ); ?>"><?php echo esc_html( $forwp_social_pub ? wp_date( get_option( 'date_format' ), $forwp_social_pub ) : '—' ); ?></td>
							<?php foreach ( $forwp_social_cols as $forwp_social_key => $forwp_social_col ) : ?>
								<?php $forwp_social_val = (float) ( $forwp_social_v[ $forwp_social_key ] ?? 0 ); ?>
								<td class="num" data-v="<?php echo esc_attr( (string) $forwp_social_val ); ?>">
									<?php echo esc_html( $forwp_social_fmt( $forwp_social_val, (string) $forwp_social_col['format'] ) ); ?>
									<?php if ( $forwp_social_key === $forwp_social_first ) : ?>
										<span class="forwp-social-muted"><?php echo esc_html( number_format_i18n( 100 * $forwp_social_val / $forwp_social_total, 1 ) . '%' ); ?></span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
