<?php
/**
 * Queue tab: publishing jobs.
 *
 * @package ForWP\Social
 *
 * @var array<int, object>               $jobs
 * @var string                           $queue_view
 * @var string                           $month
 * @var array<int, array<string, mixed>> $events
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Queue;

defined( 'ABSPATH' ) || exit;

$forwp_social_actions = Admin_Menu::actions();
$forwp_social_badges  = array(
	Job_Repository::PUBLISHED  => 'forwp-social-badge--on',
	Job_Repository::FAILED     => 'forwp-social-badge--error',
	Job_Repository::QUEUED     => 'forwp-social-badge--warn',
	Job_Repository::PROCESSING => 'forwp-social-badge--info',
);
?>
<div class="forwp-social-intro-card">
	<h2 class="forwp-social-intro-card__title"><?php esc_html_e( 'Publishing queue', '4wp-social' ); ?></h2>
	<p class="forwp-social-intro-card__text"><?php esc_html_e( 'The queue runs every minute through WP-Cron, two uploads per run. Failed jobs retry three times with a growing pause; after that use Retry. TikTok jobs stay "processing" until TikTok finishes the upload.', '4wp-social' ); ?></p>
</div>

<?php if ( ! Queue::auto_publish() ) : ?>
	<div class="forwp-social-callout forwp-social-callout--warn">
		<p>
			<strong><?php esc_html_e( 'Auto-publishing is off on this site.', '4wp-social' ); ?></strong>
			<?php
			/* translators: %s: environment type. */
			echo esc_html( sprintf( __( 'Environment "%s" is not production: jobs wait in the queue. "Run now" uploads one job for real, "Check now" polls a processing one.', '4wp-social' ), wp_get_environment_type() ) );
			?>
		</p>
	</div>
<?php endif; ?>

<p class="forwp-social-view-switch">
	<a class="button<?php echo 'list' === $queue_view ? ' button-primary' : ''; ?>" href="<?php echo esc_url( Admin_Menu::tab_url( 'queue' ) ); ?>"><?php esc_html_e( 'List', '4wp-social' ); ?></a>
	<a class="button<?php echo 'month' === $queue_view ? ' button-primary' : ''; ?>" href="<?php echo esc_url( Admin_Menu::tab_url( 'queue', array( 'view' => 'month' ) ) ); ?>"><?php esc_html_e( 'Month', '4wp-social' ); ?></a>
</p>

<?php if ( 'month' === $queue_view ) : ?>
	<?php
	$calendar_titles = false;
	$site            = array();
	$month_url       = static function ( string $forwp_social_month ): string {
		return Admin_Menu::tab_url(
			'queue',
			array(
				'view'  => 'month',
				'month' => $forwp_social_month,
			)
		);
	};
	require FORWP_SOCIAL_PATH . 'views/partials/queue-month.php';
	?>
<?php elseif ( ! $jobs ) : ?>
	<p class="forwp-social-empty"><?php esc_html_e( 'No publications yet. Use the 4WP Social box in the post editor, or publish a 4WP Drive package post.', '4wp-social' ); ?></p>
<?php else : ?>
	<?php
	// One row per post: the latest job of each platform (jobs come newest first).
	$forwp_social_posts = array();
	foreach ( $jobs as $forwp_social_job ) {
		$forwp_social_pid = (int) $forwp_social_job->post_id;
		if ( ! isset( $forwp_social_posts[ $forwp_social_pid ] ) ) {
			$forwp_social_posts[ $forwp_social_pid ] = array(
				'updated' => (int) strtotime( $forwp_social_job->updated_at . ' UTC' ),
				'jobs'    => array(),
			);
		}
		if ( ! isset( $forwp_social_posts[ $forwp_social_pid ]['jobs'][ $forwp_social_job->platform ] ) ) {
			$forwp_social_posts[ $forwp_social_pid ]['jobs'][ $forwp_social_job->platform ] = $forwp_social_job;
		}
	}

	$forwp_social_filters = array(
		'action'    => array( __( 'Needs action', '4wp-social' ), array( Job_Repository::QUEUED, Job_Repository::PROCESSING, Job_Repository::FAILED ) ),
		'failed'    => array( __( 'Failed', '4wp-social' ), array( Job_Repository::FAILED ) ),
		'published' => array( __( 'Published', '4wp-social' ), array( Job_Repository::PUBLISHED ) ),
		'all'       => array( __( 'All', '4wp-social' ), array() ),
	);
	$forwp_social_counts  = array_fill_keys( array_keys( $forwp_social_filters ), 0 );
	foreach ( $forwp_social_posts as $forwp_social_pid => $forwp_social_row ) {
		$forwp_social_statuses = array_unique( wp_list_pluck( $forwp_social_row['jobs'], 'status' ) );
		$forwp_social_posts[ $forwp_social_pid ]['statuses'] = $forwp_social_statuses;
		foreach ( $forwp_social_filters as $forwp_social_fkey => $forwp_social_f ) {
			if ( ! $forwp_social_f[1] || array_intersect( $forwp_social_f[1], $forwp_social_statuses ) ) {
				++$forwp_social_counts[ $forwp_social_fkey ];
			}
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
	$forwp_social_filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
	if ( ! isset( $forwp_social_filters[ $forwp_social_filter ] ) ) {
		$forwp_social_filter = $forwp_social_counts['action'] ? 'action' : 'all';
	}
	$forwp_social_wanted = $forwp_social_filters[ $forwp_social_filter ][1];

	$forwp_social_job_form = static function ( object $job, string $action, string $label, string $class ) use ( $forwp_social_filter ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job->id ); ?>" />
			<input type="hidden" name="forwp_social_filter" value="<?php echo esc_attr( $forwp_social_filter ); ?>" />
			<?php wp_nonce_field( $action . '_' . $job->id ); ?>
			<button class="button button-small <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	};
	?>
	<ul class="subsubsub forwp-social-queue-filters">
		<?php foreach ( $forwp_social_filters as $forwp_social_fkey => $forwp_social_f ) : ?>
			<li>
				<a href="<?php echo esc_url( Admin_Menu::tab_url( 'queue', array( 'filter' => $forwp_social_fkey ) ) ); ?>"<?php echo $forwp_social_fkey === $forwp_social_filter ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $forwp_social_f[0] ); ?> <span class="count">(<?php echo esc_html( (string) $forwp_social_counts[ $forwp_social_fkey ] ); ?>)</span></a>
			</li>
		<?php endforeach; ?>
	</ul>

	<table class="widefat striped forwp-social-table forwp-social-queue">
		<thead>
			<tr>
				<th class="forwp-social-queue__post"><?php esc_html_e( 'Post', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Platforms', '4wp-social' ); ?></th>
				<th class="forwp-social-queue__time"><?php esc_html_e( 'Updated', '4wp-social' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php $forwp_social_shown = 0; ?>
		<?php foreach ( $forwp_social_posts as $forwp_social_pid => $forwp_social_row ) : ?>
			<?php
			if ( $forwp_social_wanted && ! array_intersect( $forwp_social_wanted, $forwp_social_row['statuses'] ) ) {
				continue;
			}
			++$forwp_social_shown;
			$forwp_social_title = get_the_title( $forwp_social_pid );
			?>
			<tr>
				<td class="forwp-social-queue__post"><a href="<?php echo esc_url( (string) get_edit_post_link( $forwp_social_pid ) ); ?>" title="<?php echo esc_attr( $forwp_social_title ); ?>"><?php echo esc_html( $forwp_social_title ); ?></a></td>
				<td>
					<div class="forwp-social-chips">
					<?php foreach ( $forwp_social_row['jobs'] as $forwp_social_platform => $forwp_social_job ) : ?>
						<?php
						$forwp_social_label = (string) ( Platform_Registry::get( $forwp_social_platform )['label'] ?? $forwp_social_platform );
						$forwp_social_msg   = trim( (string) $forwp_social_job->message );
						?>
						<details class="forwp-social-chip forwp-social-chip--<?php echo esc_attr( (string) $forwp_social_job->status ); ?>">
							<summary title="<?php echo esc_attr( $forwp_social_label . ': ' . $forwp_social_job->status ); ?>">
								<?php echo Platform_Icons::render( $forwp_social_platform ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
								<span class="forwp-social-chip__dot" aria-hidden="true"></span>
								<?php if ( '' !== $forwp_social_msg && Job_Repository::PUBLISHED !== $forwp_social_job->status ) : ?>
									<span class="forwp-social-chip__alert" aria-hidden="true">!</span>
								<?php endif; ?>
							</summary>
							<div class="forwp-social-chip__panel">
								<p>
									<strong><?php echo esc_html( $forwp_social_label ); ?></strong>
									<span class="forwp-social-badge <?php echo esc_attr( $forwp_social_badges[ $forwp_social_job->status ] ?? '' ); ?>"><?php echo esc_html( $forwp_social_job->status ); ?></span>
									<?php if ( (int) $forwp_social_job->attempts > 0 ) : ?>
										<small><?php echo esc_html( sprintf( /* translators: %d: attempts */ __( 'attempt %d', '4wp-social' ), (int) $forwp_social_job->attempts ) ); ?></small>
									<?php endif; ?>
								</p>
								<?php if ( '' !== $forwp_social_job->remote_url ) : ?>
									<p><a href="<?php echo esc_url( $forwp_social_job->remote_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open on platform', '4wp-social' ); ?> ↗</a></p>
								<?php endif; ?>
								<?php if ( '' !== $forwp_social_msg ) : ?>
									<p class="forwp-social-chip__msg"><?php echo esc_html( $forwp_social_msg ); ?></p>
								<?php endif; ?>
								<?php if ( Job_Repository::PROCESSING === $forwp_social_job->status ) : ?>
									<div class="forwp-social-row-actions"><?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['run_job'], __( 'Check now', '4wp-social' ), '' ); ?></div>
								<?php elseif ( Job_Repository::is_actionable( $forwp_social_job ) ) : ?>
									<div class="forwp-social-row-actions">
										<?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['run_job'], Job_Repository::FAILED === $forwp_social_job->status ? __( 'Retry', '4wp-social' ) : __( 'Run now', '4wp-social' ), 'button-primary' ); ?>
										<?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['delete_job'], __( 'Delete', '4wp-social' ), 'button-link-delete' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</details>
					<?php endforeach; ?>
					</div>
				</td>
				<td class="forwp-social-queue__time" title="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $forwp_social_row['updated'] ) ); ?>">
					<?php
					/* translators: %s: human time difference. */
					echo esc_html( sprintf( __( '%s ago', '4wp-social' ), human_time_diff( $forwp_social_row['updated'] ) ) );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $forwp_social_shown ) : ?>
			<tr><td colspan="3" class="forwp-social-muted"><?php esc_html_e( 'Nothing here.', '4wp-social' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
<?php endif; ?>
