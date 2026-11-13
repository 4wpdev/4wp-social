<?php
/**
 * Post editor box: text articles from the meta.json package.
 *
 * @package ForWP\Social
 *
 * @var WP_Post                                                           $post
 * @var array<string, array{id: string, url: string, at: string}>         $published
 * @var ForWP\Social\Modules\Posting\Job_Repository                       $jobs
 * @var array<string, mixed>                                              $snapshot
 * @var array<string, ForWP\Social\Contracts\Platform_Adapter_Interface>  $adapters
 */

use ForWP\Social\Modules\Posting\Platform_Icons;

defined( 'ABSPATH' ) || exit;
?>
<div class="forwp-social-box" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-source="package">
	<ul class="forwp-social-platforms">
		<?php foreach ( $adapters as $forwp_social_key => $forwp_social_adapter ) : ?>
			<?php
			$forwp_social_job      = $jobs->latest( (int) $post->ID, $forwp_social_key );
			$forwp_social_done     = $published[ $forwp_social_key ] ?? null;
			$forwp_social_ready    = $forwp_social_adapter->is_ready();
			// An empty snapshot means the package is not imported yet: publishing imports it and reports what is missing.
			$forwp_social_declared = empty( $snapshot ) || isset( $snapshot[ $forwp_social_key ] );
			?>
			<li>
				<label>
					<input type="checkbox" name="forwp_social_platforms[]" value="<?php echo esc_attr( $forwp_social_key ); ?>" <?php disabled( ! $forwp_social_ready || ! $forwp_social_declared ); ?> />
					<?php echo Platform_Icons::render( $forwp_social_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<span><?php echo esc_html( $forwp_social_adapter->get_label() ); ?></span>
				</label>
				<span class="forwp-social-status" data-platform="<?php echo esc_attr( $forwp_social_key ); ?>" data-status="<?php echo esc_attr( $forwp_social_done ? 'published' : ( $forwp_social_job ? (string) $forwp_social_job->status : '' ) ); ?>">
					<?php if ( $forwp_social_done && '' !== $forwp_social_done['url'] ) : ?>
						<a href="<?php echo esc_url( $forwp_social_done['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'posted', '4wp-social' ); ?></a>
					<?php elseif ( $forwp_social_job ) : ?>
						<?php echo esc_html( $forwp_social_job->status ); ?>
					<?php elseif ( ! $forwp_social_ready ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=forwp-social-settings&tab=accounts' ) ); ?>"><?php esc_html_e( 'connect', '4wp-social' ); ?></a>
					<?php elseif ( ! $forwp_social_declared ) : ?>
						<?php esc_html_e( 'not in meta.json', '4wp-social' ); ?>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<p>
		<label><input type="checkbox" class="forwp-social-force" /> <?php esc_html_e( 'Publish again (updates the article)', '4wp-social' ); ?></label>
	</p>
	<p>
		<button type="button" class="button button-primary forwp-social-publish"><?php esc_html_e( 'Publish to selected', '4wp-social' ); ?></button>
	</p>
	<div class="forwp-social-result" aria-live="polite"></div>
</div>
