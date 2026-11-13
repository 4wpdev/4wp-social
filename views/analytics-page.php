<?php
/**
 * Analytics: one card per source, "Fetch now", last raw snapshot as JSON.
 *
 * @package ForWP\Social
 *
 * @var string                                                             $notice
 * @var string                                                             $error
 * @var array<string, ForWP\Social\Contracts\Analytics_Source_Interface>   $sources
 * @var array<string, array{fetched_at: int, data: array<string, mixed>}>  $snapshots
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Contracts\Analytics_Import_Interface;
use ForWP\Social\Modules\Analytics\Analytics;

defined( 'ABSPATH' ) || exit;

$forwp_social_notices = Admin_Menu::notices();
$forwp_social_action  = Admin_Menu::actions()['fetch_analytics'];
$forwp_social_import  = Admin_Menu::actions()['import_analytics'];
$forwp_social_labels  = array(
	Analytics::READY         => __( 'connected', '4wp-social' ),
	Analytics::NOT_CONNECTED => __( 'not connected', '4wp-social' ),
	Analytics::PLANNED       => __( 'planned', '4wp-social' ),
	Analytics::MANUAL        => __( 'manual import', '4wp-social' ),
);
?>
<div class="wrap forwp-social-admin-shell forwp-social-admin-shell--wide">
	<h1 class="forwp-social-admin-heading">
		<span class="forwp-social-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-chart-bar"></span></span>
		<span class="forwp-social-admin-heading__text"><span><?php esc_html_e( 'Analytics', '4wp-social' ); ?></span></span>
	</h1>
	<p class="forwp-social-admin-lead"><?php esc_html_e( 'Raw data from each platform, fetched on demand. Comparison and reports come next.', '4wp-social' ); ?></p>

	<?php if ( isset( $forwp_social_notices[ $notice ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $forwp_social_notices[ $notice ] ); ?></p></div>
	<?php endif; ?>
	<?php if ( '' !== $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="forwp-social-analytics">
		<?php foreach ( $sources as $forwp_social_slug => $forwp_social_source ) : ?>
			<?php
			$forwp_social_status = $forwp_social_source->status();
			$forwp_social_snap   = $snapshots[ $forwp_social_slug ] ?? null;
			?>
			<section class="forwp-social-dash__box" id="<?php echo esc_attr( 'forwp-social-source-' . sanitize_html_class( str_replace( '.', '-', $forwp_social_slug ) ) ); ?>">
				<h2>
					<?php echo esc_html( $forwp_social_source->label() ); ?>
					<span class="forwp-social-badge<?php echo Analytics::READY === $forwp_social_status ? ' forwp-social-badge--on' : ''; ?>"><?php echo esc_html( $forwp_social_labels[ $forwp_social_status ] ?? $forwp_social_status ); ?></span>
				</h2>
				<p class="description"><?php echo esc_html( $forwp_social_source->note() ); ?></p>

				<?php if ( Analytics::READY === $forwp_social_status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_action ); ?>" />
						<input type="hidden" name="source" value="<?php echo esc_attr( $forwp_social_slug ); ?>" />
						<?php wp_nonce_field( $forwp_social_action . '_' . $forwp_social_slug ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Fetch now', '4wp-social' ); ?></button>
					</form>
				<?php elseif ( Analytics::NOT_CONNECTED === $forwp_social_status ) : ?>
					<p><a href="<?php echo esc_url( Admin_Menu::tab_url( 'accounts' ) ); ?>"><?php esc_html_e( 'Connect the account', '4wp-social' ); ?></a></p>
				<?php endif; ?>

				<?php if ( $forwp_social_source instanceof Analytics_Import_Interface ) : ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_import ); ?>" />
						<input type="hidden" name="source" value="<?php echo esc_attr( $forwp_social_slug ); ?>" />
						<?php wp_nonce_field( $forwp_social_import . '_' . $forwp_social_slug ); ?>
						<input type="file" name="file" accept="<?php echo esc_attr( '.' . implode( ',.', $forwp_social_source->extensions() ) ); ?>" required />
						<button type="submit" class="button"><?php esc_html_e( 'Import file', '4wp-social' ); ?></button>
					</form>
				<?php endif; ?>

				<?php if ( $forwp_social_snap ) : ?>
					<details class="forwp-social-json">
						<summary>
							<?php
							/* translators: %s: date and time of the fetch. */
							echo esc_html( sprintf( __( 'Data from %s', '4wp-social' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $forwp_social_snap['fetched_at'] ) ) );
							?>
						</summary>
						<pre><?php echo esc_html( (string) wp_json_encode( $forwp_social_snap['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</details>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</div>
