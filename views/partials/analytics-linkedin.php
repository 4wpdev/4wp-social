<?php
/**
 * LinkedIn: account tiles from the last imported file (into the shared panel) and audience demographics (under the panel).
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed> $forwp_social_data Snapshot data: { import: { file, period, account, demographics, days, posts } }.
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_li = is_array( $forwp_social_data[ ForWP\Social\Modules\Analytics\Analytics::IMPORT_KEY ] ?? null ) ? $forwp_social_data[ ForWP\Social\Modules\Analytics\Analytics::IMPORT_KEY ] : array();
if ( ! isset( $forwp_social_li['account'] ) ) {
	return; // An old CSV import: shown as raw data only.
}

$forwp_social_num     = static function ( $value ): string {
	return null === $value || '' === $value ? '—' : number_format_i18n( (float) $value );
};
$forwp_social_account = (array) $forwp_social_li['account'];

$forwp_social_lead = array(
	/* translators: 1: file name, 2: export period. */
	'title' => sprintf( __( 'Last import · %1$s · %2$s', '4wp-social' ), (string) ( $forwp_social_li['file'] ?? '' ), (string) ( $forwp_social_li['period'] ?? '' ) ),
	'tiles' => array(
		__( 'Followers', '4wp-social' )       => $forwp_social_num( $forwp_social_account['followers'] ?? null ),
		__( 'Impressions', '4wp-social' )     => $forwp_social_num( $forwp_social_account['impressions'] ?? null ),
		__( 'Members reached', '4wp-social' ) => $forwp_social_num( $forwp_social_account['reached'] ?? null ),
	),
);

$forwp_social_demo = (array) ( $forwp_social_li['demographics'] ?? array() );
if ( ! $forwp_social_demo ) {
	return;
}

ob_start();
?>
<div class="forwp-social-an">
	<h3 class="forwp-social-an__title"><?php esc_html_e( 'Audience', '4wp-social' ); ?></h3>
	<div class="forwp-social-an__demo">
		<?php foreach ( $forwp_social_demo as $forwp_social_group => $forwp_social_rows ) : ?>
			<table class="widefat striped forwp-social-an__table">
				<thead><tr><th><?php echo esc_html( (string) $forwp_social_group ); ?></th><th class="num">%</th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( (array) $forwp_social_rows, 0, 5 ) as $forwp_social_row ) : ?>
						<tr><td><?php echo esc_html( (string) ( $forwp_social_row[0] ?? '' ) ); ?></td><td class="num"><?php echo esc_html( (string) ( $forwp_social_row[1] ?? '' ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>
</div>
<?php
$forwp_social_tail = (string) ob_get_clean();
