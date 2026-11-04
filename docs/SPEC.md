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
