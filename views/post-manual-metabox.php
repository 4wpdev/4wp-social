<?php
/**
 * Editor box: manual platforms of the post. Same rows as the other 4WP Social boxes.
 *
 * @package ForWP\Social
 *
 * @var WP_Post                              $post
 * @var array<string, array<string, string>> $entries
 * @var array<string, array<string, mixed>>  $registry
 */

use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Status_Column;

defined( 'ABSPATH' ) || exit;
?>
<div class="forwp-social-manual-box" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
	<ul class="forwp-social-platforms">
		<?php foreach ( $entries as $forwp_social_key => $forwp_social_entry ) : ?>
			<?php
			$forwp_social_key      = (string) $forwp_social_key;
			$forwp_social_platform = $registry[ $forwp_social_key ] ?? array(
				'label' => $forwp_social_key,
				'url'   => '',
			);
			$forwp_social_status   = (string) ( $forwp_social_entry['status'] ?? '' );
			$forwp_social_done     = Job_Repository::PUBLISHED === $forwp_social_status;
			$forwp_social_url      = (string) ( $forwp_social_entry['url'] ?? '' );
			?>
			<li data-platform="<?php echo esc_attr( $forwp_social_key ); ?>">
				<span class="forwp-social-platform-name">
					<?php echo Platform_Icons::render( $forwp_social_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<span><?php echo esc_html( (string) $forwp_social_platform['label'] ); ?></span>
				</span>
				<span class="forwp-social-status" data-status="<?php echo esc_attr( $forwp_social_status ); ?>">
					<?php if ( $forwp_social_done && '' !== $forwp_social_url ) : ?>
						<a href="<?php echo esc_url( $forwp_social_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'posted', '4wp-social' ); ?></a>
					<?php elseif ( $forwp_social_done ) : ?>
						<?php esc_html_e( 'posted', '4wp-social' ); ?>
					<?php else : ?>
						<?php echo esc_html( Status_Column::status_label( $forwp_social_status ) ); ?>
					<?php endif; ?>
				</span>
				<?php if ( ! $forwp_social_done ) : ?>
					<details class="forwp-social-mark-form">
						<summary><?php esc_html_e( 'Mark as published', '4wp-social' ); ?></summary>
						<input type="url" class="widefat forwp-social-mark-url" placeholder="<?php esc_attr_e( 'Link to the published page', '4wp-social' ); ?>" aria-label="<?php esc_attr_e( 'Link to the published page', '4wp-social' ); ?>" />
						<button type="button" class="button button-small forwp-social-mark"><?php esc_html_e( 'Save', '4wp-social' ); ?></button>
						<?php if ( '' !== (string) $forwp_social_platform['url'] ) : ?>
							<a href="<?php echo esc_url( (string) $forwp_social_platform['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open platform', '4wp-social' ); ?></a>
						<?php endif; ?>
					</details>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p><button type="button" class="button button-small forwp-social-notify"><?php esc_html_e( 'Send Telegram reminder', '4wp-social' ); ?></button></p>
	<div class="forwp-social-manual-msg" aria-live="polite"></div>
</div>
