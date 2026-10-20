<?php
/**
 * Documentation tab.
 *
 * @package ForWP\Social
 */

defined( 'ABSPATH' ) || exit;

$forwp_social_meta_example = <<<'JSON'
{
  "schema": 1,
  "original": { "file": "original.md" },
  "republish": { "delay_hours": 0 },
  "platforms": {
    "youtube.com": {
      "mode": "api",
      "type": "short",
      "video": "4wp-faq-overview.mp4",
      "captions": "4wp-faq-overview.en.srt",
      "cover": "4wp-faq-overview-poster.jpg",
      "file": "4wp-faq-overview-youtube.md",
      "privacy": "public"
    },
    "tiktok.com": {
      "mode": "api",
      "video": "4wp-faq-overview.mp4",
      "cover_ms": 1500,
      "file": "4wp-faq-overview-tiktok.md"
    }
  }
}
JSON;

$forwp_social_copy_example = "## Title\n```\n4WP FAQ: FAQ Schema for the Core Accordion\n```\n\n## Description\n```\nNot another FAQ block…\n```\n\n## Tags (YouTube)\n```\nWordPress FAQ plugin, FAQ schema, 4WP FAQ\n```\n\n## Caption   ← TikTok uses this when present\n```\nFAQ schema in 60 seconds #WordPress\n```";
?>
<div class="forwp-social-intro-card">
	<h2 class="forwp-social-intro-card__title"><?php esc_html_e( 'Documentation', '4wp-social' ); ?></h2>
	<p class="forwp-social-intro-card__text"><?php esc_html_e( 'Three ways in, one queue out. Whatever starts a publication, it goes through the same queue, adapters and log.', '4wp-social' ); ?></p>
</div>

<div class="forwp-social-panel">
	<h3 class="forwp-social-section-title"><?php esc_html_e( '1. Button in the post editor', '4wp-social' ); ?></h3>
	<ol class="forwp-social-howto">
		<li><?php esc_html_e( 'Open a post. In the sidebar find the 4WP Social box.', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'Choose the video (MP4), optional captions (SRT) and cover from the Media Library. No cover = featured image.', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'Tick the platforms, optionally set title, description and privacy. Empty title = post title; empty description = excerpt + link.', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'Press "Publish to selected". The job appears in the Queue tab and runs within a minute.', '4wp-social' ); ?></li>
	</ol>
</div>

<div class="forwp-social-panel">
	<h3 class="forwp-social-section-title"><?php esc_html_e( '2. 4WP Drive package (meta.json)', '4wp-social' ); ?></h3>
	<ol class="forwp-social-howto">
		<li><?php esc_html_e( 'Put the video, captions, cover and a copy file next to meta.json in the package folder (folder root, no subfolders).', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'Declare each platform with "mode": "api". Platforms with "mode": "manual" are left to 4WP SEO Helper.', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'When 4WP Drive imports the package, 4WP Social copies the media into the Media Library and stores the texts on the post.', '4wp-social' ); ?></li>
		<li><?php esc_html_e( 'When the post is published, the platforms are queued. After posting, the status, URL and id are written back to meta.json through 4WP Drive.', '4wp-social' ); ?></li>
	</ol>
	<pre class="forwp-social-code"><?php echo esc_html( $forwp_social_meta_example ); ?></pre>
	<p class="forwp-social-muted" style="margin-top:12px"><?php esc_html_e( 'Copy file format (first fenced block under each heading):', '4wp-social' ); ?></p>
	<pre class="forwp-social-code"><?php echo esc_html( $forwp_social_copy_example ); ?></pre>
</div>

<div class="forwp-social-panel">
	<h3 class="forwp-social-section-title"><?php esc_html_e( '3. AI agents (4WP MCP Connector)', '4wp-social' ); ?></h3>
	<p class="forwp-social-muted"><?php esc_html_e( 'On WordPress 6.9+ the plugin registers abilities in the forwp-social category. Allow them in 4WP MCP Connector.', '4wp-social' ); ?></p>
	<table class="widefat striped">
		<tbody>
			<tr><td><code>forwp-social/platforms</code></td><td><?php esc_html_e( 'Platforms and whether each account is connected.', '4wp-social' ); ?></td></tr>
			<tr><td><code>forwp-social/publish</code></td><td><?php esc_html_e( 'Queue a post: source "package" (meta.json) or "post" (editor box media). Never re-posts what is already posted.', '4wp-social' ); ?></td></tr>
			<tr><td><code>forwp-social/status</code></td><td><?php esc_html_e( 'Jobs of a post with status, URL and error.', '4wp-social' ); ?></td></tr>
		</tbody>
	</table>
</div>
