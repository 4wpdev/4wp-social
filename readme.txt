=== 4WP Social ===
Contributors: 4wpdev, anatolikkk
Tags: youtube, tiktok, cross-posting, social, video
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.5.7
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
* **DEV.to (Forem API)** — the article text, title, tags, cover URL and canonical URL, sent with your API key. [Terms](https://dev.to/terms), [Privacy](https://dev.to/privacy).

OAuth tokens and app secrets are stored encrypted in your database.

== Frequently Asked Questions ==

= Why are my TikTok posts private? =

TikTok restricts apps that have not passed its audit to private (SELF_ONLY) posts. Submit the audit in the TikTok developer portal.

= Does it need 4WP Drive? =

No. The editor button works on its own. 4WP Drive adds automatic publishing from `meta.json` packages.

== Changelog ==

= 0.5.7 =
* Version number fixed: the plugin header and Stable tag now match the release tag.

= 0.5.5 – 0.5.6 =
* New admin structure: Dashboard · Calendar · Analytics · Settings. Platforms moved under Settings; old links redirect.
* Dashboard: month tiles, scheduled posts, failed jobs, active platforms (manual / API / API not connected), analytics status. Full page width.
* Analytics (skeleton): YouTube (YouTube Analytics API, last 28 days), TikTok, Facebook and Instagram (Meta Graph insights). Data is fetched only with the "Fetch now" button and shown as raw JSON. Google Analytics is planned.
* Manual import: upload a CSV export for platforms without an API (LinkedIn first). Other platforms can be added with the `forwp_social_analytics_sources` filter.
* REST `forwp-social/v1/analytics` and `analytics/refresh`. Filter `forwp_social_oauth_scopes` for extra OAuth scopes.
* YouTube asks for the `yt-analytics.readonly` scope; reconnect Google to grant it.
* Queue: first and default tab, status chips, update time with date.

= 0.5.4 =
* Facebook Reels and Instagram Reels via Meta (Graph API).
* Queue: "Run now" and "Delete" for jobs.
* No automatic publishing outside the production environment.

= 0.5.3 =
* The 4WP Social Media box shows on pages and on any post type that has a meta.json package, not only on videos.
* Cross-posting board (browser extension) lists packaged posts of every public post type.
* TikTok: videos up to 64 MB upload as one chunk. A 10–20 MB file was sent as one 10 MB chunk and TikTok rejected it ("The chunk size is invalid").

= 0.5.0 =
* Cross-posting center: Platforms page with a dynamic list. Each platform is manual or bound to one plugin connector; a connector binds to one platform only.
* Status index per post and platform: "Social" column in post lists, Calendar page with site content first, month statistics and a day popup, Queue → List and Month, REST `forwp-social/v1/calendar`, read locally without GitHub requests.
* Manual platforms: waiting status after a delay, "Mark as published" in the editor, Telegram reminder (off by default), REST `forwp-social/v1/crosspost*` for the browser extension.
* Abilities: `forwp-social/calendar`, `forwp-social/scheduled`; `forwp-social/platforms` now lists manual platforms too.
* WP-CLI: `wp forwp-social reindex`, `wp forwp-social migrate-seo [--dry-run]`.

= 0.4.0 =
* New platform: DEV.to. Connect with an API key; the key is verified before saving and stored encrypted.
* New editor box for text articles on posts with a meta.json package. The text comes from the platform "file" (front matter, "# Title", Markdown body), the canonical URL points to the original.
* Privacy "public" publishes; other values, or "published: false" in the file, save a DEV.to draft. "Publish again" updates the same article.

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
