<?php
/**
 * Analytics: platform tabs, shared header (period + "Fetch now"), one card per source, last raw snapshot as JSON.
 *
 * @package ForWP\Social
 *
 * @var string                                                             $notice
 * @var string                                                             $error
 * @var array<string, ForWP\Social\Contracts\Analytics_Source_Interface>   $sources
 * @var array<string, array{fetched_at: int, data: array<string, mixed>}>  $snapshots
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Contracts\Analytics_Daily_Interface;
use ForWP\Social\Contracts\Analytics_Import_Interface;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Analytics\Metrics_Store;
use ForWP\Social\Modules\Analytics\Sources\Google_Analytics_Source;

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
	<p class="forwp-social-admin-lead"><?php esc_html_e( 'Data from each platform: collected once a day, or by hand with "Fetch now".', '4wp-social' ); ?></p>
	<?php
	$forwp_social_next  = wp_next_scheduled( Analytics::CRON_HOOK );
	$forwp_social_last  = get_option( Analytics::LAST_DAILY, array() );
	$forwp_social_dtfmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	?>
	<p class="forwp-social-muted">
		<?php
		echo esc_html(
			$forwp_social_next
				/* translators: %s: date and time. */
				? sprintf( __( 'Daily collection: next run %s', '4wp-social' ), wp_date( $forwp_social_dtfmt, (int) $forwp_social_next ) )
				: __( 'Daily collection is off', '4wp-social' )
		);
		if ( ! empty( $forwp_social_last['time'] ) ) {
			/* translators: %s: date and time. */
			echo esc_html( ' · ' . sprintf( __( 'last run %s', '4wp-social' ), wp_date( $forwp_social_dtfmt, (int) $forwp_social_last['time'] ) ) );
		}
		?>
		· <a href="<?php echo esc_url( Admin_Menu::tab_url( 'modules' ) ); ?>"><?php esc_html_e( 'Settings', '4wp-social' ); ?></a>
	</p>

	<?php if ( isset( $forwp_social_notices[ $notice ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $forwp_social_notices[ $notice ] ); ?></p></div>
	<?php endif; ?>
	<?php if ( '' !== $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php
	// One platform per page: ?source=<slug>, default = first source.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
	$forwp_social_current = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
	if ( ! isset( $sources[ $forwp_social_current ] ) ) {
		$forwp_social_current = (string) array_key_first( $sources );
	}
	$forwp_social_partials = array(
		'youtube.com'          => 'analytics-youtube.php',
		'tiktok.com'           => 'analytics-tiktok.php',
		'instagram.com'        => 'analytics-instagram.php',
		'facebook.com'         => 'analytics-facebook.php',
		'analytics.google.com' => 'analytics-google.php',
		'linkedin.com'         => 'analytics-linkedin.php',
	);
	?>
	<?php
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
	$forwp_social_range = Metrics_Store::resolve_range(
		isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : Metrics_Store::DEFAULT_PRESET,
		isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
		isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
		time()
	);
	// phpcs:enable
	$forwp_social_cur_src    = $sources[ $forwp_social_current ];
	$forwp_social_presets    = array(
		'7d'         => __( 'Last 7 days', '4wp-social' ),
		'28d'        => __( 'Last 28 days', '4wp-social' ),
		'90d'        => __( 'Last 90 days', '4wp-social' ),
		'365d'       => __( 'Last 365 days', '4wp-social' ),
		'month'      => __( 'This month', '4wp-social' ),
		'prev_month' => __( 'Last month', '4wp-social' ),
		'custom'     => __( 'Custom…', '4wp-social' ),
	);
	$forwp_social_range_args = array( 'range' => $forwp_social_range['preset'] );
	if ( 'custom' === $forwp_social_range['preset'] ) {
		$forwp_social_range_args['from'] = $forwp_social_range['start'];
		$forwp_social_range_args['to']   = $forwp_social_range['end'];
	}
	?>
	<div class="forwp-social-an-head">
		<nav class="nav-tab-wrapper forwp-social-tabs forwp-social-an-head__tabs" aria-label="<?php esc_attr_e( 'Analytics platforms', '4wp-social' ); ?>">
			<?php foreach ( $sources as $forwp_social_slug => $forwp_social_source ) : ?>
				<a href="<?php echo esc_url( Admin_Menu::analytics_url( array_merge( $forwp_social_range_args, array( 'source' => $forwp_social_slug ) ) ) ); ?>" class="nav-tab<?php echo $forwp_social_current === $forwp_social_slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $forwp_social_source->label() ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="forwp-social-an-head__tools">
			<?php if ( $forwp_social_cur_src instanceof Analytics_Daily_Interface ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="forwp-social-an-range">
					<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::ANALYTICS_SLUG ); ?>" />
					<input type="hidden" name="source" value="<?php echo esc_attr( $forwp_social_current ); ?>" />
					<label class="screen-reader-text" for="forwp-social-range"><?php esc_html_e( 'Period', '4wp-social' ); ?></label>
					<select name="range" id="forwp-social-range">
						<?php foreach ( $forwp_social_presets as $forwp_social_key => $forwp_social_label ) : ?>
							<option value="<?php echo esc_attr( $forwp_social_key ); ?>" <?php selected( $forwp_social_range['preset'], $forwp_social_key ); ?>><?php echo esc_html( $forwp_social_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="forwp-social-an-range__custom"<?php echo 'custom' === $forwp_social_range['preset'] ? '' : ' hidden'; ?>>
						<input type="date" name="from" value="<?php echo esc_attr( $forwp_social_range['start'] ); ?>" aria-label="<?php esc_attr_e( 'From', '4wp-social' ); ?>" />
						<input type="date" name="to" value="<?php echo esc_attr( $forwp_social_range['end'] ); ?>" aria-label="<?php esc_attr_e( 'To', '4wp-social' ); ?>" />
						<button type="submit" class="button"><?php esc_html_e( 'Apply', '4wp-social' ); ?></button>
					</span>
				</form>
			<?php endif; ?>
			<?php if ( Analytics::READY === $forwp_social_cur_src->status() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_action ); ?>" />
					<input type="hidden" name="source" value="<?php echo esc_attr( $forwp_social_current ); ?>" />
					<?php foreach ( $forwp_social_range_args as $forwp_social_key => $forwp_social_value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $forwp_social_key ); ?>" value="<?php echo esc_attr( $forwp_social_value ); ?>" />
					<?php endforeach; ?>
					<?php wp_nonce_field( $forwp_social_action . '_' . $forwp_social_current ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Fetch now', '4wp-social' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="forwp-social-analytics forwp-social-analytics--single">
		<?php foreach ( array( $forwp_social_current => $sources[ $forwp_social_current ] ) as $forwp_social_slug => $forwp_social_source ) : ?>
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

				<?php if ( Analytics::NOT_CONNECTED === $forwp_social_status ) : ?>
					<p><a href="<?php echo esc_url( Admin_Menu::tab_url( 'accounts' ) ); ?>"><?php esc_html_e( 'Connect the account', '4wp-social' ); ?></a></p>
				<?php endif; ?>

				<?php if ( $forwp_social_source instanceof Google_Analytics_Source ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="forwp-social-an-ga4">
						<input type="hidden" name="action" value="<?php echo esc_attr( Admin_Menu::actions()['save_ga4'] ); ?>" />
						<?php wp_nonce_field( Admin_Menu::actions()['save_ga4'] ); ?>
						<label for="forwp-social-ga4"><?php esc_html_e( 'GA4 Property ID', '4wp-social' ); ?></label>
						<input type="text" id="forwp-social-ga4" name="property" inputmode="numeric" pattern="[0-9]*" class="regular-text" value="<?php echo esc_attr( Google_Analytics_Source::property() ); ?>" placeholder="123456789" />
						<button type="submit" class="button"><?php esc_html_e( 'Save', '4wp-social' ); ?></button>
						<span class="description"><?php esc_html_e( 'Google Analytics → Admin → Property details → Property ID.', '4wp-social' ); ?></span>
					</form>
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

				<?php
				$forwp_social_lead = null; // Account tiles a partial hands to the shared panel.
				$forwp_social_tail = ''; // Partial HTML shown under the panel.
				if ( $forwp_social_snap && isset( $forwp_social_partials[ $forwp_social_slug ] ) ) {
					$forwp_social_data = (array) $forwp_social_snap['data'];
					include FORWP_SOCIAL_PATH . 'views/partials/' . $forwp_social_partials[ $forwp_social_slug ];
				}
				if ( $forwp_social_source instanceof Analytics_Daily_Interface && in_array( $forwp_social_status, array( Analytics::READY, Analytics::MANUAL ), true ) ) {
					include FORWP_SOCIAL_PATH . 'views/partials/analytics-panel.php';
				}
				echo $forwp_social_tail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the partial.
				?>

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
