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
use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Post_Reader;
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
	// One row per platform variant (latest job of each post + platform), grouped under its post.
	$forwp_social_rows = array();
	foreach ( $jobs as $forwp_social_job ) {
		$forwp_social_key = (int) $forwp_social_job->post_id . '|' . $forwp_social_job->platform;
		if ( ! isset( $forwp_social_rows[ $forwp_social_key ] ) ) {
			$forwp_social_rows[ $forwp_social_key ] = $forwp_social_job;
		}
	}

	$forwp_social_filters = array(
		'action'    => array( __( 'Needs action', '4wp-social' ), array( Job_Repository::QUEUED, Job_Repository::PROCESSING, Job_Repository::FAILED ) ),
		'failed'    => array( __( 'Failed', '4wp-social' ), array( Job_Repository::FAILED ) ),
		'published' => array( __( 'Published', '4wp-social' ), array( Job_Repository::PUBLISHED ) ),
		'all'       => array( __( 'All', '4wp-social' ), array() ),
	);
	$forwp_social_counts  = array_fill_keys( array_keys( $forwp_social_filters ), 0 );
	foreach ( $forwp_social_rows as $forwp_social_job ) {
		foreach ( $forwp_social_filters as $forwp_social_fkey => $forwp_social_f ) {
			if ( ! $forwp_social_f[1] || in_array( $forwp_social_job->status, $forwp_social_f[1], true ) ) {
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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
	$forwp_social_focus = isset( $_GET['job'] ) ? absint( $_GET['job'] ) : 0;

	// Group the shown rows by post, posts in the order of their newest job.
	$forwp_social_groups = array();
	foreach ( $forwp_social_rows as $forwp_social_job ) {
		if ( $forwp_social_wanted && ! in_array( $forwp_social_job->status, $forwp_social_wanted, true ) ) {
			continue;
		}
		$forwp_social_groups[ (int) $forwp_social_job->post_id ][] = $forwp_social_job;
	}

	$forwp_social_status_labels = array(
		Job_Repository::QUEUED     => __( 'Queued', '4wp-social' ),
		Job_Repository::PROCESSING => __( 'Processing', '4wp-social' ),
		Job_Repository::PUBLISHED  => __( 'Published', '4wp-social' ),
		Job_Repository::FAILED     => __( 'Failed', '4wp-social' ),
	);

	$forwp_social_date = static function ( ?string $mysql ): string {
		return $mysql ? (string) wp_date( 'Y-m-d H:i', (int) strtotime( $mysql . ' UTC' ) ) : '';
	};

	// Date line: flat dashicon + date; the label is in the tooltip and for screen readers.
	$forwp_social_when = static function ( string $icon, string $label, ?string $mysql ) use ( $forwp_social_date ): void {
		?>
		<span class="forwp-social-queue__when" title="<?php echo esc_attr( $label ); ?>">
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( $label ); ?>:</span>
			<?php echo esc_html( $forwp_social_date( $mysql ) ); ?>
		</span>
		<?php
	};

	// Text of this network's variant: the TikTok title chosen by the author, else the platform copy.
	$forwp_social_post_reader = new Post_Reader();
	$forwp_social_pkg_reader  = new Package_Reader();
	$forwp_social_variant     = static function ( object $job ) use ( $forwp_social_post_reader, $forwp_social_pkg_reader ): string {
		$options = Job_Repository::options( $job );
		$text    = (string) ( $options['tiktok']['title'] ?? '' );
		if ( '' === $text ) {
			$reader  = Job_Repository::SOURCE_POST === (string) $job->source ? $forwp_social_post_reader : $forwp_social_pkg_reader;
			$package = $reader->build( (int) $job->post_id, (string) $job->platform, false, $options );
			if ( ! is_wp_error( $package ) ) {
				$text = '' !== trim( $package->description() ) ? $package->description() : $package->title();
			}
		}

		return trim( (string) strtok( trim( $text ), "\n" ) );
	};

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

	<table class="widefat forwp-social-table forwp-social-queue">
		<thead>
			<tr>
				<th class="forwp-social-queue__platform"><?php esc_html_e( 'Network', '4wp-social' ); ?></th>
				<th class="forwp-social-queue__content"><?php esc_html_e( 'Content', '4wp-social' ); ?></th>
				<th class="forwp-social-queue__status"><?php esc_html_e( 'Status', '4wp-social' ); ?></th>
				<th class="forwp-social-queue__dates"><?php esc_html_e( 'Dates', '4wp-social' ); ?></th>
				<th class="forwp-social-queue__actions"><?php esc_html_e( 'Actions', '4wp-social' ); ?></th>
			</tr>
		</thead>
		<?php if ( ! $forwp_social_groups ) : ?>
			<tbody><tr><td colspan="5" class="forwp-social-muted"><?php esc_html_e( 'Nothing here.', '4wp-social' ); ?></td></tr></tbody>
		<?php endif; ?>
		<?php foreach ( $forwp_social_groups as $forwp_social_pid => $forwp_social_group ) : ?>
			<?php $forwp_social_title = get_the_title( $forwp_social_pid ); ?>
			<tbody class="forwp-social-queue__group" id="forwp-social-post-<?php echo esc_attr( (string) $forwp_social_pid ); ?>">
				<tr class="forwp-social-queue__post-row">
					<th colspan="5" scope="rowgroup">
						<a href="<?php echo esc_url( (string) get_edit_post_link( $forwp_social_pid ) ); ?>"><?php echo esc_html( '' !== $forwp_social_title ? $forwp_social_title : '#' . $forwp_social_pid ); ?></a>
						<span class="forwp-social-muted">
							<?php
							/* translators: 1: post status, 2: post date. */
							echo esc_html( sprintf( __( '%1$s · %2$s', '4wp-social' ), (string) get_post_status( $forwp_social_pid ), (string) get_the_date( 'Y-m-d H:i', $forwp_social_pid ) ) );
							?>
						</span>
					</th>
				</tr>
				<?php foreach ( $forwp_social_group as $forwp_social_job ) : ?>
					<?php
					$forwp_social_platform = (string) $forwp_social_job->platform;
					$forwp_social_label    = (string) ( Platform_Registry::get( $forwp_social_platform )['label'] ?? $forwp_social_platform );
					$forwp_social_status   = (string) $forwp_social_job->status;
					$forwp_social_msg      = trim( (string) $forwp_social_job->message );
					$forwp_social_text     = $forwp_social_variant( $forwp_social_job );
					$forwp_social_is_pub   = Job_Repository::PUBLISHED === $forwp_social_status;
					$forwp_social_open     = (int) $forwp_social_job->id === $forwp_social_focus;
					?>
					<?php // Accordion row: collapsed shows icon, start of the text, status, main date, link / actions; click to expand. ?>
					<tr id="forwp-social-job-<?php echo esc_attr( (string) $forwp_social_job->id ); ?>" class="forwp-social-queue__job forwp-social-queue__job--<?php echo esc_attr( $forwp_social_status ); ?><?php echo $forwp_social_open ? ' is-focus' : ''; ?>" tabindex="0" aria-expanded="<?php echo $forwp_social_open ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Click to expand or collapse', '4wp-social' ); ?>">
						<td class="forwp-social-queue__platform">
							<span class="forwp-social-queue__net">
								<span class="forwp-social-queue__toggle dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
								<span class="forwp-social-queue__icon"><?php echo Platform_Icons::render( $forwp_social_platform ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></span>
								<span class="forwp-social-queue__label"><?php echo esc_html( $forwp_social_label ); ?></span>
							</span>
						</td>
						<td class="forwp-social-queue__content">
							<span class="forwp-social-queue__text"><?php echo esc_html( '' !== $forwp_social_text ? $forwp_social_text : '—' ); ?></span>
						</td>
						<td class="forwp-social-queue__status">
							<span class="forwp-social-badge <?php echo esc_attr( $forwp_social_badges[ $forwp_social_status ] ?? '' ); ?>"><?php echo esc_html( $forwp_social_status_labels[ $forwp_social_status ] ?? $forwp_social_status ); ?></span>
							<?php if ( (int) $forwp_social_job->attempts > 0 && ! $forwp_social_is_pub ) : ?>
								<small><?php echo esc_html( sprintf( /* translators: %d: attempts */ __( 'attempt %d', '4wp-social' ), (int) $forwp_social_job->attempts ) ); ?></small>
							<?php endif; ?>
							<?php if ( '' !== $forwp_social_msg ) : ?>
								<span class="forwp-social-queue__msg"><?php echo esc_html( $forwp_social_msg ); ?></span>
							<?php endif; ?>
						</td>
						<td class="forwp-social-queue__dates">
							<?php if ( $forwp_social_is_pub ) : ?>
								<?php $forwp_social_when( 'calendar-alt', __( 'Published', '4wp-social' ), $forwp_social_job->updated_at ); ?>
							<?php else : ?>
								<?php $forwp_social_when( 'calendar-alt', __( 'Planned', '4wp-social' ), $forwp_social_job->run_at ); ?>
								<?php if ( Job_Repository::QUEUED === $forwp_social_status ) : ?>
									<?php $forwp_social_when( 'update', __( 'Updated', '4wp-social' ), $forwp_social_job->updated_at ); ?>
								<?php endif; ?>
								<div class="forwp-social-queue__more">
									<?php if ( ! empty( $forwp_social_job->started_at ) ) : ?>
										<?php $forwp_social_when( 'controls-play', __( 'Started', '4wp-social' ), $forwp_social_job->started_at ); ?>
									<?php endif; ?>
									<?php if ( Job_Repository::QUEUED !== $forwp_social_status ) : ?>
										<?php $forwp_social_when( 'update', __( 'Updated', '4wp-social' ), $forwp_social_job->updated_at ); ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</td>
						<td class="forwp-social-queue__actions">
							<?php if ( '' !== $forwp_social_job->remote_url ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $forwp_social_job->remote_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', '4wp-social' ); ?> ↗</a>
							<?php endif; ?>
							<?php if ( Job_Repository::PROCESSING === $forwp_social_status ) : ?>
								<?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['run_job'], __( 'Check now', '4wp-social' ), '' ); ?>
							<?php elseif ( Job_Repository::is_actionable( $forwp_social_job ) ) : ?>
								<?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['run_job'], Job_Repository::FAILED === $forwp_social_status ? __( 'Retry', '4wp-social' ) : __( 'Run now', '4wp-social' ), 'button-primary' ); ?>
								<?php $forwp_social_job_form( $forwp_social_job, $forwp_social_actions['delete_job'], __( 'Delete', '4wp-social' ), 'button-link-delete' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
