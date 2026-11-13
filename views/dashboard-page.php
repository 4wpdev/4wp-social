<?php
/**
 * Dashboard: the month at a glance, scheduled work, failed jobs, active platforms, analytics.
 *
 * @package ForWP\Social
 *
 * @var string                                                                  $month
 * @var array<int, array<string, mixed>>                                        $events
 * @var array<string, int>                                                      $stats
 * @var array<int, array<string, mixed>>                                        $scheduled
 * @var array<int, object>                                                      $failed
 * @var array<string, array<string, mixed>>                                     $platforms
 * @var array<string, ForWP\Social\Contracts\Platform_Adapter_Interface>        $connectors
 * @var array<string, ForWP\Social\Contracts\Analytics_Source_Interface>        $sources
 * @var array<string, array{fetched_at: int, data: array<string, mixed>}>       $snapshots
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Status_Column;
use ForWP\Social\Modules\Posting\Status_Index;

defined( 'ABSPATH' ) || exit;

$forwp_social_tiles    = array(
	array( $stats['published'], __( 'Published', '4wp-social' ), Job_Repository::PUBLISHED ),
	array( $stats['scheduled'], __( 'Scheduled', '4wp-social' ), Job_Repository::QUEUED ),
	array( $stats['waiting'], __( 'Waiting for manual posting', '4wp-social' ), Status_Index::MANUAL_PENDING ),
	array( $stats['failed'], __( 'Failed', '4wp-social' ), Job_Repository::FAILED ),
);
$forwp_social_datetime = 'Y-m-d H:i';
?>
<div class="wrap forwp-social-admin-shell forwp-social-admin-shell--wide">
	<h1 class="forwp-social-admin-heading">
		<span class="forwp-social-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-share"></span></span>
		<span class="forwp-social-admin-heading__text">
			<span><?php esc_html_e( '4WP Social', '4wp-social' ); ?></span>
			<span class="forwp-social-admin-heading__version"><?php echo esc_html( 'v' . FORWP_SOCIAL_VERSION ); ?></span>
		</span>
	</h1>

	<h2>
		<?php
		/* translators: %s: month, e.g. 2026-11. */
		echo esc_html( sprintf( __( 'This month (%s)', '4wp-social' ), $month ) );
		?>
		<a class="page-title-action" href="<?php echo esc_url( Admin_Menu::calendar_url( $month ) ); ?>"><?php esc_html_e( 'Open Calendar', '4wp-social' ); ?></a>
	</h2>
	<div class="forwp-social-stats">
		<?php foreach ( $forwp_social_tiles as $forwp_social_tile ) : ?>
			<div class="forwp-social-stat">
				<span class="forwp-social-stat__value"><?php echo esc_html( number_format_i18n( $forwp_social_tile[0] ) ); ?></span>
				<span class="forwp-social-stat__label"><?php echo Status_Column::marker( $forwp_social_tile[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants. ?> <?php echo esc_html( $forwp_social_tile[1] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php require FORWP_SOCIAL_PATH . 'views/partials/platform-counts.php'; ?>

	<div class="forwp-social-dash">
		<section class="forwp-social-dash__box">
			<h2><?php esc_html_e( 'Scheduled', '4wp-social' ); ?></h2>
			<?php if ( ! $scheduled ) : ?>
				<p class="description"><?php esc_html_e( 'Nothing scheduled.', '4wp-social' ); ?></p>
			<?php else : ?>
				<ul class="forwp-social-dash__list">
					<?php foreach ( $scheduled as $forwp_social_item ) : ?>
						<li>
							<span class="forwp-social-dash__time"><?php echo esc_html( (string) wp_date( $forwp_social_datetime, (int) strtotime( (string) $forwp_social_item['run_at'] ) ) ); ?></span>
							<?php echo Platform_Icons::render( (string) $forwp_social_item['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
							<?php echo Status_Column::marker( (string) $forwp_social_item['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants. ?>
							<a href="<?php echo esc_url( (string) $forwp_social_item['edit_url'] ); ?>"><?php echo esc_html( (string) $forwp_social_item['title'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( Admin_Menu::tab_url( 'queue' ) ); ?>"><?php esc_html_e( 'Open the queue', '4wp-social' ); ?></a></p>
		</section>

		<section class="forwp-social-dash__box">
			<h2><?php esc_html_e( 'Failed jobs', '4wp-social' ); ?></h2>
			<?php if ( ! $failed ) : ?>
				<p class="description"><?php esc_html_e( 'No failed jobs.', '4wp-social' ); ?></p>
			<?php else : ?>
				<ul class="forwp-social-dash__list">
					<?php foreach ( array_slice( $failed, 0, 5 ) as $forwp_social_job ) : ?>
						<li>
							<span class="forwp-social-dash__time"><?php echo esc_html( (string) wp_date( $forwp_social_datetime, (int) strtotime( $forwp_social_job->updated_at . ' UTC' ) ) ); ?></span>
							<?php echo Platform_Icons::render( (string) $forwp_social_job->platform ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $forwp_social_job->post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $forwp_social_job->post_id ) ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( Admin_Menu::tab_url( 'queue', array( 'filter' => Job_Repository::FAILED ) ) ); ?>">
					<?php
					/* translators: %d: number of failed jobs. */
					echo esc_html( sprintf( _n( 'Open the queue (%d failed)', 'Open the queue (%d failed)', count( $failed ), '4wp-social' ), count( $failed ) ) );
					?>
				</a></p>
			<?php endif; ?>
		</section>

		<section class="forwp-social-dash__box">
			<h2><?php esc_html_e( 'Active platforms', '4wp-social' ); ?></h2>
			<ul class="forwp-social-dash__list">
				<?php foreach ( $platforms as $forwp_social_key => $forwp_social_row ) : ?>
					<?php
					$forwp_social_connector = (string) ( $forwp_social_row['connector'] ?? '' );
					$forwp_social_adapter   = $connectors[ $forwp_social_connector ] ?? null;
					?>
					<li>
						<?php echo Platform_Icons::render( (string) $forwp_social_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
						<?php echo esc_html( (string) $forwp_social_row['label'] ); ?>
						<?php if ( '' === $forwp_social_connector ) : ?>
							<span class="forwp-social-badge"><?php esc_html_e( 'manual', '4wp-social' ); ?></span>
						<?php elseif ( $forwp_social_adapter && $forwp_social_adapter->is_ready() ) : ?>
							<span class="forwp-social-badge forwp-social-badge--on"><?php esc_html_e( 'API', '4wp-social' ); ?></span>
						<?php else : ?>
							<span class="forwp-social-badge forwp-social-badge--error"><?php esc_html_e( 'API not connected', '4wp-social' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><a href="<?php echo esc_url( Admin_Menu::tab_url( 'platforms' ) ); ?>"><?php esc_html_e( 'Manage platforms', '4wp-social' ); ?></a></p>
		</section>

		<section class="forwp-social-dash__box">
			<h2><?php esc_html_e( 'Analytics', '4wp-social' ); ?></h2>
			<ul class="forwp-social-dash__list">
				<?php foreach ( $sources as $forwp_social_slug => $forwp_social_source ) : ?>
					<li>
						<?php echo esc_html( $forwp_social_source->label() ); ?> —
						<?php if ( isset( $snapshots[ $forwp_social_slug ] ) ) : ?>
							<?php echo esc_html( (string) wp_date( $forwp_social_datetime, (int) $snapshots[ $forwp_social_slug ]['fetched_at'] ) ); ?>
						<?php elseif ( Analytics::PLANNED === $forwp_social_source->status() ) : ?>
							<?php esc_html_e( 'planned', '4wp-social' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'no data yet', '4wp-social' ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><a href="<?php echo esc_url( Admin_Menu::analytics_url() ); ?>"><?php esc_html_e( 'Open Analytics', '4wp-social' ); ?></a></p>
		</section>
	</div>
</div>
