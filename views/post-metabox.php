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

use ForWP\Social\Modules\Posting\Adapters\TikTok_Adapter;
use ForWP\Social\Modules\Posting\Platform_Icons;

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
			$forwp_social_queue = $forwp_social_job && 'queued' === (string) $forwp_social_job->status ? $forwp_social_job : null;
			$forwp_social_when  = $forwp_social_queue ? wp_date( 'Y-m-d\TH:i', (int) strtotime( $forwp_social_queue->run_at . ' UTC' ) ) : '';
			?>
			<li>
				<label>
					<input type="checkbox" name="forwp_social_platforms[]" value="<?php echo esc_attr( $forwp_social_key ); ?>" <?php disabled( ! $forwp_social_ready ); ?> />
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
					<?php endif; ?>
				</span>
				<?php if ( ! $forwp_social_done && $forwp_social_ready ) : ?>
					<input type="datetime-local" class="forwp-social-when" data-platform="<?php echo esc_attr( $forwp_social_key ); ?>" data-job-id="<?php echo esc_attr( $forwp_social_queue ? (string) $forwp_social_queue->id : '' ); ?>" value="<?php echo esc_attr( $forwp_social_when ); ?>" title="<?php esc_attr_e( 'Publish date on this platform (empty: with the post)', '4wp-social' ); ?>" aria-label="<?php esc_attr_e( 'Publish date on this platform', '4wp-social' ); ?>" />
				<?php endif; ?>
				<?php if ( $forwp_social_done || $forwp_social_ready ) : ?>
					<div class="forwp-social-link" data-platform="<?php echo esc_attr( $forwp_social_key ); ?>">
						<?php if ( $forwp_social_done ) : ?>
							<?php
							// Bluesky ids are at:// URIs: the last segment (rkey) is enough to recognise the post.
							$forwp_social_rid = (string) $forwp_social_done['id'];
							$forwp_social_rid = 0 === strpos( $forwp_social_rid, 'at://' ) ? substr( $forwp_social_rid, (int) strrpos( $forwp_social_rid, '/' ) + 1 ) : $forwp_social_rid;
							?>
							<code title="<?php echo esc_attr( (string) $forwp_social_done['id'] ); ?>"><?php echo esc_html( $forwp_social_rid ); ?></code>
							<button type="button" class="button-link forwp-social-unlink"><?php esc_html_e( 'Unlink', '4wp-social' ); ?></button>
						<?php elseif ( 'youtube.com' !== $forwp_social_key ) : ?>
							<details>
								<?php /* translators: %s: platform name. */ ?>
								<summary><?php echo esc_html( sprintf( __( 'Already on %s?', '4wp-social' ), $forwp_social_adapter->get_label() ) ); ?></summary>
								<input type="url" class="widefat forwp-social-link-url" placeholder="<?php esc_attr_e( 'Link to the published post', '4wp-social' ); ?>" />
								<button type="button" class="button button-small forwp-social-link-url-save"><?php esc_html_e( 'Link', '4wp-social' ); ?></button>
							</details>
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

	<?php if ( isset( $adapters[ TikTok_Adapter::SLUG ] ) && $adapters[ TikTok_Adapter::SLUG ]->is_ready() ) : ?>
		<?php // TikTok Content Sharing Guidelines: account, preview, editable title, privacy without default, interactions, disclosure, consent. ?>
		<fieldset class="forwp-social-tiktok" hidden>
			<legend><?php esc_html_e( 'TikTok', '4wp-social' ); ?></legend>
			<p class="forwp-social-tiktok__account">
				<img class="forwp-social-tiktok__avatar" alt="" width="24" height="24" hidden />
				<?php esc_html_e( 'Posting to:', '4wp-social' ); ?> <strong class="forwp-social-tiktok__name">…</strong>
			</p>
			<p class="forwp-social-tiktok__msg" aria-live="polite"></p>
			<video class="forwp-social-tiktok__preview" controls muted playsinline preload="metadata" hidden></video>
			<p>
				<label for="forwp-social-tiktok-title"><strong><?php esc_html_e( 'Title', '4wp-social' ); ?></strong></label>
				<textarea class="widefat" rows="3" maxlength="2200" id="forwp-social-tiktok-title" data-tiktok="title"></textarea>
			</p>
			<p>
				<label for="forwp-social-tiktok-privacy"><strong><?php esc_html_e( 'Who can view this video', '4wp-social' ); ?></strong></label>
				<select class="widefat" id="forwp-social-tiktok-privacy" data-tiktok="privacy_level">
					<option value=""><?php esc_html_e( '— Select —', '4wp-social' ); ?></option>
				</select>
			</p>
			<p class="forwp-social-tiktok__allow">
				<strong><?php esc_html_e( 'Allow users to', '4wp-social' ); ?></strong><br />
				<label><input type="checkbox" data-tiktok="allow_comment" /> <?php esc_html_e( 'Comment', '4wp-social' ); ?></label>
				<label><input type="checkbox" data-tiktok="allow_duet" /> <?php esc_html_e( 'Duet', '4wp-social' ); ?></label>
				<label><input type="checkbox" data-tiktok="allow_stitch" /> <?php esc_html_e( 'Stitch', '4wp-social' ); ?></label>
			</p>
			<p>
				<label><input type="checkbox" data-tiktok="commercial" /> <strong><?php esc_html_e( 'Disclose video content', '4wp-social' ); ?></strong></label><br />
				<span class="description"><?php esc_html_e( 'Turn on to disclose that this video promotes goods or services in exchange for something of value. Your video could promote yourself, a third party, or both.', '4wp-social' ); ?></span>
			</p>
			<div class="forwp-social-tiktok__brand" hidden>
				<p>
					<label><input type="checkbox" data-tiktok="brand_organic" /> <?php esc_html_e( 'Your brand', '4wp-social' ); ?></label><br />
					<span class="description"><?php esc_html_e( 'You are promoting yourself or your own business. This video will be classified as Brand Organic.', '4wp-social' ); ?></span>
				</p>
				<p>
					<label><input type="checkbox" data-tiktok="brand_content" /> <?php esc_html_e( 'Branded content', '4wp-social' ); ?></label><br />
					<span class="description"><?php esc_html_e( 'You are promoting another brand or a third party. This video will be classified as Branded Content.', '4wp-social' ); ?></span>
				</p>
				<p class="forwp-social-tiktok__label"></p>
			</div>
			<p class="forwp-social-tiktok__consent"></p>
			<p class="description"><?php esc_html_e( 'After publishing, it may take a few minutes for the video to appear on your TikTok profile. The status is shown next to TikTok above.', '4wp-social' ); ?></p>
		</fieldset>
	<?php endif; ?>

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
