# План: крос-постинг у Social + міграція з SEO Helper
Статус: погоджено 2026-11-04. Крок 4 — після узгодження актуальності дампу.

Кожен крок — окремий реліз. Після кроку — перевірка на 4wp.dev.

## Крок 1. Індекс статусів
- `_forwp_social_status` + запис з імпорту Drive, черги, Remote_Link.
- WP-CLI: `wp forwp-social reindex` для наявних постів.

## Крок 2. Колонка «Social»
- Колонка для всіх CPT з пакетом (post, video …).

## Крок 3. Календар у Queue
- `List | Month`, REST `/calendar`, фільтр для Planner.

## Крок 4. Реєстр платформ api + manual — ⚠️ узгодити
- Перед переносом: узгодити, з якого дампу/середовища беремо дані, щоб не перетерти актуальні.
- Перенести таксономію `forwp_platform` (key, mode, url, enabled, order) з SEO Helper.

## Крок 5. Manual-потік
- Кнопка «Позначити опублікованим», затримка, Telegram-нагадування, REST для розширення.

## Крок 6. Міграція даних із SEO Helper
| SEO Helper | → Social |
|---|---|
| `_forwp_seo_crosspost_log` | `_forwp_social_status` (`via: migration`) |
| `_forwp_seo_crosspost_notified_at` / `_notify_error` | поля в `_forwp_social_status` |
| таксономія `forwp_platform` | реєстр Social |
| опції `forwp_seo_crossposting_*` | налаштування Social |
| `forwp-seo/v1/crosspost*` | `forwp-social/v1/crosspost*` |

- WP-CLI: `wp forwp-social migrate-seo --dry-run`.

## Крок 7. Розширення
- Перевести на `forwp-social/v1`.

## Крок 8. Вимкнення в SEO Helper
- Старі маршрути `forwp-seo/v1/crosspost*` — проксі на Social один реліз, потім видалити.
- Модуль `CrossPosting` у SEO Helper видалити.
