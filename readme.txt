=== 4WP Social ===
Contributors: 4wpdev, anatolikkk
Tags: youtube, tiktok, cross-posting, social, video
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cross-post videos from WordPress to YouTube and TikTok by API — from a 4WP Drive package, a button in the editor, or an AI agent.

== Description ==

**4WP Social** connects your site with social platforms. Three modules:

* **Posting** (active) — the site publishes to platforms: YouTube (video, cover, SRT captions) and TikTok (Direct Post).
* **Share** (contour) — visitors share your pages.
* **Feed** (contour) — show social feeds on the site.

A plugin by [4wp.dev](https://4wp.dev/).

= Three ways to publish =

1. **Button** — the 4WP Social box in the post editor: pick the video, captions and cover from the Media Library, choose platforms, press *Publish to selected*.
2. **4WP Drive package** — `meta.json` declares `platforms` with `"mode": "api"`. During the Drive import the plugin copies the video, captions, cover and the platform copy file into WordPress; when the post is published the platforms are queued.
3. **AI agent** — abilities `forwp-social/publish`, `forwp-social/status`, `forwp-social/platforms` (WordPress Abilities API, exposed through 4WP MCP Connector).

= meta.json platform entry =

`"youtube.com": { "mode": "api", "video": "clip.mp4", "captions": "clip.en.srt", "cover": "poster.jpg", "file": "clip-youtube.md", "privacy": "public" }`

The copy file uses `## Title`, `## Description`, `## Tags` (or `## Caption`) sections with fenced blocks.

= External services =

This plugin sends your video, captions, cover, title and description to the platforms you connect, only when you publish:

* **YouTube Data API v3** (Google) — upload, thumbnail, captions. [Terms](https://developers.google.com/youtube/terms/api-services-terms-of-service), [Privacy](https://policies.google.com/privacy).
* **TikTok Content Posting API** — upload and status. [Terms](https://www.tiktok.com/legal/page/global/tik-tok-developer-terms-of-service/en), [Privacy](https://www.tiktok.com/legal/page/row/privacy-policy/en).
* **Bluesky (AT Protocol, bsky.social)** — login with an app password, video upload and post. [Terms](https://bsky.social/about/support/tos), [Privacy](https://bsky.social/about/support/privacy-policy).

OAuth tokens and app secrets are stored encrypted in your database.

== Frequently Asked Questions ==

= Why are my TikTok posts private? =

TikTok restricts apps that have not passed its audit to private (SELF_ONLY) posts. Submit the audit in the TikTok developer portal.

= Does it need 4WP Drive? =

No. The editor button works on its own. 4WP Drive adds automatic publishing from `meta.json` packages.

== Changelog ==

= 0.3.0 =
* New platform: Bluesky (AT Protocol). Connect with a handle and an app password (no developer app); the password is verified before saving and stored encrypted.
* Bluesky posting: text with link and hashtag facets (300-grapheme limit, hashtags added while they fit), video embed up to 100 MB with aspect ratio; public posts only.
* Accounts tab: Bluesky card with connect, test connection and disconnect.

= 0.2.0 =
* Video module: video post type, series taxonomy and tags (filled from the package), SRT captions.
* Post video block (`forwp-social/post-video`): player for the single video page, tap-to-play card for archives and loops.
* Link an existing YouTube video to a post instead of uploading it again.
* Editor box: package media by default, publish button states with job polling; shown only on opted-in post types.

= 0.1.0 =
* Modules: Posting (YouTube, TikTok), Share and Feed contours.
* Drive package import into the Media Library, publishing queue, editor button, REST and abilities.
