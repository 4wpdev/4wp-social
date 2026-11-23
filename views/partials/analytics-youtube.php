<?php
/**
 * YouTube: channel totals (all time). When the shared panel follows, the tiles go into its first row.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { channel }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_yt_channel = $forwp_social_data['channel']['items'][0] ?? array();
$forwp_social_yt_stats   = is_array( $forwp_social_yt_channel['statistics'] ?? null ) ? $forwp_social_yt_channel['statistics'] : array();

$forwp_social_num = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};

$forwp_social_yt_tiles = array(
	__( 'Subscribers', '4wp-social' ) => $forwp_social_num( $forwp_social_yt_stats['subscriberCount'] ?? null ),
	__( 'Total views', '4wp-social' ) => $forwp_social_num( $forwp_social_yt_stats['viewCount'] ?? null ),
	__( 'Videos', '4wp-social' )      => $forwp_social_num( $forwp_social_yt_stats['videoCount'] ?? null ),
);

// The shared panel (views/partials/analytics-panel.php) shows these tiles in one row with the period.
$forwp_social_lead          = isset( $forwp_social_data['channel']['error'] ) ? null : array(
	'title' => __( 'Channel · all time', '4wp-social' ),
	'tiles' => $forwp_social_yt_tiles,
);
$forwp_social_lead_in_panel = ForWP\Social\Modules\Analytics\Analytics::READY === $forwp_social_status;
?>
<div class="forwp-social-an">
	<?php if ( $forwp_social_lead_in_panel && null !== $forwp_social_lead ) : ?>
		<?php // Rendered by the panel. ?>
	<?php elseif ( isset( $forwp_social_data['channel']['error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['channel']['error'] ); ?></p>
	<?php else : ?>
		<h3 class="forwp-social-an__title"><?php esc_html_e( 'Channel · all time', '4wp-social' ); ?></h3>
		<div class="forwp-social-an__tiles">
			<?php foreach ( $forwp_social_yt_tiles as $forwp_social_label => $forwp_social_value ) : ?>
				<div class="forwp-social-an__tile"><strong><?php echo esc_html( $forwp_social_value ); ?></strong><span><?php echo esc_html( $forwp_social_label ); ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $forwp_social_data['metrics_error'] ) ) : ?>
		<p class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_data['metrics_error'] ); ?></p>
	<?php endif; ?>
</div>
