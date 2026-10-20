<?php
/**
 * Modules tab: module cards + posting settings.
 *
 * @package ForWP\Social
 *
 * @var array<string, mixed>                                   $settings
 * @var array<string, ForWP\Social\Contracts\Module_Interface> $modules
 */

use ForWP\Social\Admin\Admin_Menu;

defined( 'ABSPATH' ) || exit;

$forwp_social_actions = Admin_Menu::actions();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_actions['save_settings'] ); ?>" />
	<?php wp_nonce_field( $forwp_social_actions['save_settings'] ); ?>

	<div class="forwp-social-intro-card">
		<h2 class="forwp-social-intro-card__title"><?php esc_html_e( 'Modules', '4wp-social' ); ?></h2>
		<p class="forwp-social-intro-card__text"><?php esc_html_e( 'Click a card to switch it On or Off, then save. Posting and Video are ready to use; Share and Feed are contours — their structure is in place, the features come next.', '4wp-social' ); ?></p>
	</div>

	<h3 class="forwp-social-section-title"><?php esc_html_e( 'Available modules', '4wp-social' ); ?></h3>
	<p class="forwp-social-muted"><?php esc_html_e( 'Share (visitor → network), Posting (site → network), Feed (network → site).', '4wp-social' ); ?></p>

	<div class="forwp-social-module-grid">
		<?php foreach ( $modules as $forwp_social_slug => $forwp_social_module ) : ?>
			<?php $forwp_social_on = ! empty( $settings['modules'][ $forwp_social_slug ] ); ?>
			<div class="forwp-social-module-card<?php echo $forwp_social_on ? ' is-enabled' : ''; ?>" role="button" tabindex="0" aria-pressed="<?php echo $forwp_social_on ? 'true' : 'false'; ?>">
				<input class="forwp-social-module-card__check" type="checkbox" name="forwp_social_settings[modules][<?php echo esc_attr( $forwp_social_slug ); ?>]" value="1" <?php checked( $forwp_social_on ); ?> />
				<div class="forwp-social-module-card__head">
					<div>
						<div class="forwp-social-module-card__label"><?php echo esc_html( $forwp_social_module->get_label() ); ?></div>
						<div class="forwp-social-module-card__slug"><code><?php echo esc_html( $forwp_social_slug ); ?></code></div>
					</div>
					<div class="forwp-social-module-card__badges">
						<?php if ( ! $forwp_social_module->is_implemented() ) : ?>
							<span class="forwp-social-badge forwp-social-badge--info"><?php esc_html_e( 'Contour', '4wp-social' ); ?></span>
						<?php endif; ?>
						<span class="forwp-social-badge forwp-social-badge--on" <?php echo $forwp_social_on ? '' : 'hidden'; ?>><?php esc_html_e( 'On', '4wp-social' ); ?></span>
						<span class="forwp-social-badge forwp-social-badge--off" <?php echo $forwp_social_on ? 'hidden' : ''; ?>><?php esc_html_e( 'Off', '4wp-social' ); ?></span>
					</div>
				</div>
				<p class="forwp-social-module-card__status"><?php echo esc_html( $forwp_social_module->get_description() ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="forwp-social-panel">
		<h3 class="forwp-social-section-title"><?php esc_html_e( 'Posting', '4wp-social' ); ?></h3>
		<p class="forwp-social-muted"><?php esc_html_e( 'Defaults for every publication. A meta.json package or the editor box can override privacy per post.', '4wp-social' ); ?></p>

		<label class="forwp-social-toggle-row">
			<input type="checkbox" name="forwp_social_settings[auto_publish]" value="1" <?php checked( ! empty( $settings['auto_publish'] ) ); ?> />
			<span>
				<strong><?php esc_html_e( 'Publish 4WP Drive packages automatically', '4wp-social' ); ?></strong><br />
				<span class="forwp-social-muted"><?php esc_html_e( 'When a post imported from a meta.json package is published, queue every platform with "mode": "api".', '4wp-social' ); ?></span>
			</span>
		</label>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-delay"><?php esc_html_e( 'Delay after the post goes live (minutes)', '4wp-social' ); ?></label>
			<input type="number" min="0" max="10080" id="forwp-social-delay" name="forwp_social_settings[delay_minutes]" value="<?php echo esc_attr( (string) $settings['delay_minutes'] ); ?>" />
			<p class="forwp-social-field__help"><?php esc_html_e( 'meta.json "republish": { "delay_hours", "delay_minutes" } overrides this per package. The editor button always publishes at once.', '4wp-social' ); ?></p>
		</div>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-privacy-default"><?php esc_html_e( 'Default privacy', '4wp-social' ); ?></label>
			<select id="forwp-social-privacy-default" name="forwp_social_settings[default_privacy]">
				<?php
				$forwp_social_privacy = array(
					'public'   => __( 'Public', '4wp-social' ),
					'unlisted' => __( 'Unlisted (YouTube) / private (TikTok)', '4wp-social' ),
					'private'  => __( 'Private', '4wp-social' ),
				);
				foreach ( $forwp_social_privacy as $forwp_social_value => $forwp_social_label ) :
					?>
					<option value="<?php echo esc_attr( $forwp_social_value ); ?>" <?php selected( $settings['default_privacy'], $forwp_social_value ); ?>><?php echo esc_html( $forwp_social_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-lang"><?php esc_html_e( 'Captions language', '4wp-social' ); ?></label>
			<input type="text" id="forwp-social-lang" name="forwp_social_settings[caption_language]" value="<?php echo esc_attr( (string) $settings['caption_language'] ); ?>" />
			<p class="forwp-social-field__help"><?php esc_html_e( 'Used when the SRT file name has no language code (video.en.srt → en).', '4wp-social' ); ?></p>
		</div>
	</div>

	<div class="forwp-social-panel">
		<h3 class="forwp-social-section-title"><?php esc_html_e( 'Video', '4wp-social' ); ?></h3>
		<p class="forwp-social-muted"><?php esc_html_e( 'Names of the video post type and its series taxonomy. If the site already has a type with this name, Video adds its fields to it instead of registering a new one.', '4wp-social' ); ?></p>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-video-type"><?php esc_html_e( 'Post type', '4wp-social' ); ?></label>
			<input type="text" maxlength="20" id="forwp-social-video-type" name="forwp_social_settings[video][post_type]" value="<?php echo esc_attr( (string) $settings['video']['post_type'] ); ?>" />
		</div>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-video-tax"><?php esc_html_e( 'Series taxonomy', '4wp-social' ); ?></label>
			<input type="text" maxlength="32" id="forwp-social-video-tax" name="forwp_social_settings[video][taxonomy]" value="<?php echo esc_attr( (string) $settings['video']['taxonomy'] ); ?>" />
		</div>

		<div class="forwp-social-field">
			<label class="forwp-social-field__label" for="forwp-social-video-slug"><?php esc_html_e( 'URL base', '4wp-social' ); ?></label>
			<input type="text" id="forwp-social-video-slug" name="forwp_social_settings[video][slug]" value="<?php echo esc_attr( (string) $settings['video']['slug'] ); ?>" />
			<p class="forwp-social-field__help"><?php esc_html_e( 'Videos live at /{base}/{video}/, series at /{base}/series/{series}/. Rewrite rules refresh by themselves.', '4wp-social' ); ?></p>
		</div>

		<label class="forwp-social-toggle-row">
			<input type="checkbox" name="forwp_social_settings[video][json_ld]" value="1" <?php checked( ! empty( $settings['video']['json_ld'] ) ); ?> />
			<span>
				<strong><?php esc_html_e( 'Output VideoObject JSON-LD', '4wp-social' ); ?></strong><br />
				<span class="forwp-social-muted"><?php esc_html_e( 'Poster, duration, original file, YouTube embed, copies and transcript on every video page.', '4wp-social' ); ?></span>
			</span>
		</label>
	</div>

	<div class="forwp-social-form-actions">
		<?php submit_button( __( 'Save modules', '4wp-social' ), 'primary', 'submit', false ); ?>
	</div>
</form>
