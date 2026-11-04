# 4WP Social

Cross-posting for WordPress: **YouTube**, **TikTok**, **Bluesky** and **DEV.to** via API, plus Share and Feed contours. Part of the [4WP](https://4wp.dev/) family; conventions follow 4WP Drive.

Architecture: `4wp-publish-hub/plugin/4wp-social/architecture.md`.

## Entry points → one queue

| Entry | How |
|---|---|
| Button | Editor box "4WP Social" → `POST /wp-json/forwp-social/v1/jobs` (`source: post`) |
| 4WP Drive | `forwp_drive_document_imported` → media into WP; post published → jobs (`source: package`) |
| MCP | Abilities `forwp-social/publish`, `/status`, `/platforms`, `/calendar`, `/scheduled` (`docs/ABILITIES.md`) |

Manual platforms (X, Medium, Hashnode, daily.dev, LinkedIn) wait for a person or the browser extension: `forwp-social/v1/crosspost*`. Every status lives in `_forwp_social_status`; the "Social" column and the calendar read only that.

## WP-CLI

```
wp forwp-social reindex [--post=<id>]
wp forwp-social migrate-seo [--dry-run]
```

`migrate-seo` copies data from 4WP SEO Helper (which must be active) and never changes the source.

## Layout

```
src/
├── Contracts/   Module, Platform_Adapter, Article_Adapter, Media_Source, Share_Service, Feed_Provider
├── Core/        Module_Registry, Settings, Media_Package, Publish_Result
├── Auth/        OAuth_Controller, Google/TikTok providers, Crypto, stores
├── Api/         Http, YouTube_Client, TikTok_Client, Bluesky_Client, DevTo_Client
├── Modules/
│   ├── Posting/ Package_Importer, Package_Reader, Post_Reader, Posting_Service,
│   │            Queue, Drive_Bridge, Post_Metabox, Abilities, Status_Index,
│   │            Platform_Registry, Manual_Flow, Status_Column, Calendar,
│   │            Seo_Migration, Adapters/
│   ├── Video/   Video_Module, Video_Types, Post_Video_Block, Srt
│   ├── Share/   contour
│   └── Feed/    contour
├── Cli/         Commands (reindex, migrate-seo)
├── Rest/        Rest_Oauth (callback), Rest_Jobs, Rest_Link, Rest_Crosspost, Rest_Platforms
└── Admin/       Admin_Menu (Settings: Modules · Accounts · Queue; Platforms and Calendar pages)
```

## Hooks

| Hook | Type |
|---|---|
| `forwp_social_modules`, `forwp_social_adapters`, `forwp_social_oauth_providers` | filter |
| `forwp_social_share_services`, `forwp_social_feed_providers` | filter |
| `forwp_social_media_package`, `forwp_social_package_media_path`, `forwp_social_post_types` | filter |
| `forwp_social_status_post_types`, `forwp_social_calendar_events`, `forwp_social_calendar_site_post_types` | filter |
| `forwp_social_package_imported`, `forwp_social_published`, `forwp_social_failed` | action |

## Development

```
composer install
composer test
composer lint
```
