<?php
/**
 * Queue tab: publishing jobs.
 *
 * @package ForWP\Social
 *
 * @var array<int, object> $jobs
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Modules\Posting\Job_Repository;

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

<?php if ( ! $jobs ) : ?>
	<p class="forwp-social-empty"><?php esc_html_e( 'No publications yet. Use the 4WP Social box in the post editor, or publish a 4WP Drive package post.', '4wp-social' ); ?></p>
<?php else : ?>
	<table class="widefat striped forwp-social-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Post', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Platform', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Source', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Status', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Result', '4wp-social' ); ?></th>
				<th><?php esc_html_e( 'Updated', '4wp-social' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $jobs as $forwp_social_job ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $forwp_social_job->post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $forwp_social_job->post_id ) ); ?></a></td>
				<td><?php echo esc_html( $forwp_social_job->platform ); ?></td>
				<td><?php echo esc_html( 'post' === $forwp_social_job->source ? __( 'Editor', '4wp-social' ) : __( 'Drive package', '4wp-social' ) ); ?></td>
				<td>
					<span class="forwp-social-badge <?php echo esc_attr( $forwp_social_badges[ $forwp_social_job->status ] ?? '' ); ?>"><?php echo esc_html( $forwp_social_job->status ); ?></span>
					<?php if ( (int) $forwp_social_job->attempts > 0 ) : ?>
						<small><?php echo esc_html( sprintf( /* translators: %d: attempts */ __( 'attempt %d', '4wp-social' ), (int) $forwp_social_job->attempts ) ); ?></small>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( '' !== $forwp_social_job->remote_url ) : ?>
						<a href="<?php echo esc_url( $forwp_social_job->remote_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open on platform', '4wp-social' ); ?></a><br />
					<?php endif; ?>
					<span class="forwp-social-muted"><?php echo esc_html( (string) $forwp_social_job->message ); ?></span>
				</td>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $forwp_social_job->updated_at . ' UTC' ) ) ); ?></td>
				<td>
					<?php if ( Job_Repository::FAILED === $forwp_social_job->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_actions['retry_job'] ); ?>" />
							<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $forwp_social_job->id ); ?>" />
							<?php wp_nonce_field( $forwp_social_actions['retry_job'] . '_' . $forwp_social_job->id ); ?>
							<button class="button button-small"><?php esc_html_e( 'Retry', '4wp-social' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
