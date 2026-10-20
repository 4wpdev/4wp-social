<?php
/**
 * Post editor box.
 *
 * @package ForWP\Social
 *
 * @var WP_Post                                                           $post
 * @var array<string, mixed>                                              $saved
 * @var array<string, array{id: string, url: string, at: string}>         $published
 * @var ForWP\Social\Modules\Posting\Job_Repository                       $jobs
 * @var bool                                                              $is_package
 * @var array<string, ForWP\Social\Contracts\Platform_Adapter_Interface>  $adapters
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_files = array(
	'video_id'    => array( __( 'Video (MP4)', '4wp-social' ), 'video' ),
	'captions_id' => array( __( 'Captions (SRT)', '4wp-social' ), '' ),
	'cover_id'    => array( __( 'Cover (JPG/PNG)', '4wp-social' ), 'image' ),
);
?>
<div class="forwp-social-box" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
	<ul class="forwp-social-platforms">
		<?php foreach ( $adapters as $forwp_social_key => $forwp_social_adapter ) : ?>
			<?php
			$forwp_social_job   = $jobs->latest( (int) $post->ID, $forwp_social_key );
			$forwp_social_done  = $published[ $forwp_social_key ] ?? null;
			$forwp_social_ready = $forwp_social_adapter->is_ready();
			?>
			<li>
				<label>
					<input type="checkbox" name="forwp_social_platforms[]" value="<?php echo esc_attr( $forwp_social_key ); ?>" <?php disabled( ! $forwp_social_ready ); ?> />
					<?php echo esc_html( $forwp_social_adapter->get_label() ); ?>
				</label>
				<span class="forwp-social-status" data-platform="<?php echo esc_attr( $forwp_social_key ); ?>" data-status="<?php echo esc_attr( $forwp_social_done ? 'published' : ( $forwp_social_job ? (string) $forwp_social_job->status : '' ) ); ?>">
					<?php if ( $forwp_social_done && '' !== $forwp_social_done['url'] ) : ?>
						<a href="<?php echo esc_url( $forwp_social_done['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'posted', '4wp-social' ); ?></a>
					<?php elseif ( $forwp_social_job ) : ?>
						<?php echo esc_html( $forwp_social_job->status ); ?>
					<?php elseif ( ! $forwp_social_ready ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=forwp-social&tab=accounts' ) ); ?>"><?php esc_html_e( 'connect', '4wp-social' ); ?></a>
					<?php endif; ?>
				</span>
				<?php if ( 'youtube.com' === $forwp_social_key && $forwp_social_ready ) : ?>
					<div class="forwp-social-link" data-platform="<?php echo esc_attr( $forwp_social_key ); ?>">
						<?php if ( $forwp_social_done ) : ?>
							<code><?php echo esc_html( (string) $forwp_social_done['id'] ); ?></code>
							<button type="button" class="button-link forwp-social-unlink"><?php esc_html_e( 'Unlink', '4wp-social' ); ?></button>
						<?php else : ?>
							<details>
								<summary><?php esc_html_e( 'Already on YouTube?', '4wp-social' ); ?></summary>
								<button type="button" class="button button-small forwp-social-find"><?php esc_html_e( 'Find by title', '4wp-social' ); ?></button>
								<div class="forwp-social-found"></div>
								<input type="text" class="widefat forwp-social-link-input" placeholder="<?php esc_attr_e( 'Video ID or link', '4wp-social' ); ?>" />
								<button type="button" class="button button-small forwp-social-link-save"><?php esc_html_e( 'Link', '4wp-social' ); ?></button>
							</details>
						<?php endif; ?>
						<div class="forwp-social-link-msg" aria-live="polite"></div>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $is_package ) : ?>
		<p class="forwp-social-source">
			<label><input type="radio" name="forwp_social_source" value="package" checked /> <?php esc_html_e( 'Media from meta.json package', '4wp-social' ); ?></label><br />
			<label><input type="radio" name="forwp_social_source" value="post" /> <?php esc_html_e( 'Media from this box', '4wp-social' ); ?></label>
		</p>
	<?php endif; ?>

	<?php // Package posts take media, title and description from meta.json; manual fields only for "this box". ?>
	<div class="forwp-social-manual"<?php echo $is_package ? ' hidden' : ''; ?>>
	<?php foreach ( $forwp_social_files as $forwp_social_field => $forwp_social_meta ) : ?>
		<?php $forwp_social_id = (int) ( $saved[ $forwp_social_field ] ?? 0 ); ?>
		<p class="forwp-social-file">
			<strong><?php echo esc_html( $forwp_social_meta[0] ); ?></strong><br />
			<input type="hidden" name="forwp_social[<?php echo esc_attr( $forwp_social_field ); ?>]" value="<?php echo esc_attr( (string) $forwp_social_id ); ?>" data-field="<?php echo esc_attr( $forwp_social_field ); ?>" />
			<span class="forwp-social-file-name"><?php echo esc_html( $forwp_social_id ? basename( (string) get_attached_file( $forwp_social_id ) ) : '—' ); ?></span>
			<button type="button" class="button button-small forwp-social-pick" data-library="<?php echo esc_attr( $forwp_social_meta[1] ); ?>"><?php esc_html_e( 'Choose', '4wp-social' ); ?></button>
			<button type="button" class="button-link forwp-social-clear"><?php esc_html_e( 'Clear', '4wp-social' ); ?></button>
		</p>
	<?php endforeach; ?>
	</div>

	<p>
		<label for="forwp-social-privacy"><strong><?php esc_html_e( 'Privacy', '4wp-social' ); ?></strong></label><br />
		<select id="forwp-social-privacy" name="forwp_social[privacy]" data-field="privacy">
			<option value=""><?php esc_html_e( 'Default', '4wp-social' ); ?></option>
			<?php foreach ( array( 'public', 'unlisted', 'private' ) as $forwp_social_privacy ) : ?>
				<option value="<?php echo esc_attr( $forwp_social_privacy ); ?>" <?php selected( (string) ( $saved['privacy'] ?? '' ), $forwp_social_privacy ); ?>><?php echo esc_html( $forwp_social_privacy ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<div class="forwp-social-manual"<?php echo $is_package ? ' hidden' : ''; ?>>
	<p>
		<label for="forwp-social-title"><strong><?php esc_html_e( 'Title', '4wp-social' ); ?></strong></label>
		<input type="text" class="widefat" id="forwp-social-title" name="forwp_social[title]" data-field="title" value="<?php echo esc_attr( (string) ( $saved['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Post title', '4wp-social' ); ?>" />
	</p>
	<p>
		<label for="forwp-social-description"><strong><?php esc_html_e( 'Description', '4wp-social' ); ?></strong></label>
		<textarea class="widefat" rows="4" id="forwp-social-description" name="forwp_social[description]" data-field="description" placeholder="<?php esc_attr_e( 'Excerpt + link', '4wp-social' ); ?>"><?php echo esc_textarea( (string) ( $saved['description'] ?? '' ) ); ?></textarea>
	</p>
	</div>

	<p>
		<label><input type="checkbox" class="forwp-social-force" /> <?php esc_html_e( 'Publish again even if already posted', '4wp-social' ); ?></label>
	</p>
	<p>
		<button type="button" class="button button-primary forwp-social-publish"><?php esc_html_e( 'Publish to selected', '4wp-social' ); ?></button>
	</p>
	<div class="forwp-social-result" aria-live="polite"></div>
</div>
