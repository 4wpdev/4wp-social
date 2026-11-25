# 4WP Social — специфікація крос-постингу

## 1. Платформи
- Один реєстр: `key`, `label`, `connector` (конектор плагіну або порожньо = manual; один конектор лише на одну платформу), `mode` (`api|manual`, похідний від `connector`), `url` (куди відкривати), `enabled`, `order`.
- Джерело — реєстр Social: опція `forwp_social_platforms`, стартовий список лише до першого збереження (дані таксономії `forwp_platform` з SEO Helper переносить міграція).
- `mode` у meta.json має пріоритет над реєстром.

## 2. Статус «пост × платформа»
Post meta `_forwp_social_status`:
```
{ "<platform>": { "mode", "status", "url", "remote_id", "run_at", "updated_at", "via" } }
```
`status`: `planned` · `queued` · `processing` · `published` · `failed` · `manual_pending`.
`via`: `api` · `manual` · `extension` · `meta.json` · `migration`.

Платформи зі `status: skipped` у meta.json в індекс не потрапляють. `slug` сторінки зберігається в `remote_id`. Результат нагадування: `notified_at`, `notify_error` на кожній manual-платформі поста.

Пишуть: черга (api), кнопка «Позначити опублікованим» (manual), REST розширення, імпорт Drive.

## 3. Індекс meta.json
- На `forwp_drive_document_imported` та після оновлення пакета — зберегти всі платформи (api + manual) у `_forwp_social_status`.
- Список постів і календар **не читають GitHub**.

## 4. Колонка «Social»
- `post` + типи з `forwp_social_post_types` (фільтр `forwp_social_status_post_types`); показ лише для постів із `_forwp_drive_package` або `_forwp_social_status`.
- Іконка платформи + маркер (dashicons): галочка published · годинник queued/planned (дата) · пісочний годинник processing · попередження failed · коло manual_pending.
- Іконка веде на `url`.

## 5. Календар
- Окрема сторінка Calendar: панелі статистики місяця (Published, Scheduled, Waiting, Failed) і календар із назвами постів.
- У кожному дні першим іде контент сайту (Page, Post, Video, Case, далі інші публічні типи): публікація й останнє оновлення. Фільтр `forwp_social_calendar_site_post_types`.
- Вкладка Queue лишається технічним джерелом.
- Вкладка Queue: перемикач `List | Month`.
- REST `GET forwp-social/v1/calendar?month=YYYY-MM` → події з `_forwp_social_status` + jobs.
- Дата події: `run_at` (заплановані), `updated_at` (решта).
- Клітинка дня: іконка мережі + маркер статусу; клік → пост.
- Фільтр `forwp_social_calendar_events` — для Planner.

## 6. Manual-потік
1. Після публікації оригіналу + затримка (`republish.delay_*` з meta.json або `manual_delay_minutes`) → `manual_pending`.
2. Нагадування в Telegram (`manual_notify`, вимкнено, поки нагадування шле SEO Helper).
3. Розширення бере payload, публікує, повертає URL → `published`.

## 7. REST (`forwp-social/v1`)
| Маршрут | Метод | Заміна SEO Helper |
|---|---|---|
| `/crosspost?post_id=` | GET | `forwp-seo/v1/crosspost` |
| `/crosspost/payload` | GET | `forwp-seo/v1/crosspost/payload` |
| `/crosspost/published` | POST | `forwp-seo/v1/crosspost/published` |
| `/crosspost/notify` | POST | `forwp-seo/v1/crosspost/notify` |
| `/crosspost/board` | GET | `forwp-seo/v1/crosspost/board` |
| `/calendar` | GET | — (нове) |

## Аналітика (0.7.0)
- Знімок `forwp_social_analytics_snapshots`: підсумки акаунта і сирий JSON.
- Таблиця `{prefix}forwp_social_metrics` (`platform`, `day`, `item`, `metrics` JSON): щоденні підсумки. Пише `Metrics_Store::collect()` з cron і «Fetch now»: перший запуск — `history_days()`, далі останні 7 днів.
- Джерело для панелі реалізує `Analytics_Daily_Interface`: `daily()`, `metrics()`, `history_days()`, `items()`, `item_columns()`.
- Панель `views/partials/analytics-panel.php`: період (`range` = 7d | 28d | 90d | 365d | month | prev_month | custom + `from`/`to`), плитки з ▲/▼ проти попереднього періоду такої ж довжини, діаграма з tooltip і топ-5 за день, таблиця з сортуванням (за замовчуванням — дата публікації, нові зверху).
- REST `GET forwp-social/v1/analytics/items` — елементи за період, кеш на добу (скидається після кожного collect).
- TikTok: `TikTok_OAuth_Provider::REQUIRED_SCOPES` (`user.info.basic`, `video.publish`) — завжди; `OPTIONAL_SCOPES` (`user.info.profile`, `user.info.stats`, `video.list`) — лише з увімкненими чекбоксами (`Credentials_Store` extra `scope_*`). Крок 3 показує `requested_scopes()`. `TikTok_Source::fetch()` без потрібних scopes повертає помилку з їх переліком (ADR 0007).
- Джерела з загальними сумами (TikTok) реалізують `Analytics_Cumulative_Interface::snapshot()`: щоденний знімок пишеться як `platform:raw` (акаунт + кожне відео), приріст між знімками — рядком за вчора; відео за період — різниця знімків на початок і кінець періоду. Мета відео — опція `forwp_social_items_meta_{slug}`.
- Змішане джерело (Facebook, `history_days() > 1`): рахунок по днях — з API (insights сторінки, частинами по 90 днів), знімки лише для Reels; приріст рахунку зі знімків не пишеться.
- Instagram — теж змішане: по днях лише reach (time_series, до 30 днів за запит), знімки — для постів.
- Google Analytics (GA4 Data API, Google-підключення + scope `analytics.readonly`, Property ID в опції `forwp_social_ga4_property`): метрики по днях, топ сторінок, канали і джерела трафіку за період.
- LinkedIn (ручний імпорт `.xlsx` Aggregate analytics, також CSV): дні — у таблицю метрик (кожен файл дописує свої дні), пости — опція `forwp_social_linkedin_posts` (цифри за період експорту, у таблиці — пости з датою публікації в періоді), демографія і підсумки — у знімку `import`.
- Підключено: YouTube, TikTok, Facebook, Instagram, Google Analytics, LinkedIn (вручну).

