# Abilities 4WP Social

Категорія `forwp-social`. Контракт: `4wp-mcp-connector/docs/ABILITY-CONTRACT.md`. Агенту доступні лише ті, що адмін увімкнув у конекторі.

| Ability | Тип | Вхід | Право |
|---|---|---|---|
| `forwp-social/platforms` | читання | — | `publish_posts` |
| `forwp-social/calendar` | читання | `month` (YYYY-MM) | `edit_others_posts` |
| `forwp-social/scheduled` | читання | `platform`, `limit` (1–200, типово 50) | `edit_others_posts` |
| `forwp-social/status` | читання | `post_id` | `edit_post` |
| `forwp-social/publish` | запис | `post_id`, `platforms`, `source`, `privacy` | `publish_posts` + `edit_post` |

## platforms
Усі платформи, зокрема вимкнені: `platform`, `label`, `mode`, `connector`, `ready`, `enabled`, `url`.

## calendar
```json
{ "month": "2026-10" }
```
Повертає `month`, `stats` (`published`, `scheduled`, `waiting`, `failed`) і `events` (`date`, `time`, `post_id`, `title`, `edit_url`, `platform`, `status`, `mode`, `url`) і `site`: контент сайту за місяць (`date`, `time`, `post_id`, `post_type`, `type_label`, `action`: `publish` або `update`, `title`, `edit_url`, `url`). WordPress зберігає лише останнє оновлення поста, тож попередні оновлення не видно.

## scheduled
```json
{ "platform": "dev.to", "limit": 10 }
```
Статуси `planned`, `queued`, `processing` за всі місяці, найближчі першими: `post_id`, `title`, `edit_url`, `platform`, `status`, `mode`, `run_at`. Плани без дати не входять.
