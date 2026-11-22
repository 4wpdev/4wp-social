# 0.6.2 — Дата на кожну мережу і «Already on …?» для всіх мереж

## Задача 2 (виконано в 0.6.2) — заплановані пости на «сьогодні»
**Причина:** задача створювалась з `run_at = time()`; календар бере `run_at` для `queued`. Поки cron не переставить `run_at` на дату поста (`wait_for_publish`), подія стоїть на день постановки в чергу.
**Фікс:** `Posting_Service::not_before_publish()` — для `future`-поста `run_at = max(run_at, дата публікації)` у `enqueue_manual` і `enqueue_post`.

## Задача 3
### 3a. Окрема дата на мережу
| Крок | Статус |
|---|---|
| Поле `datetime-local` біля кожної мережі в «4WP Social Media» (червоні рамки на скріні) | готово |
| Нова задача: дата з поля → `run_at` (не раніше дати поста) | готово |
| Задача вже `queued`: зміна дати → `POST jobs/{id}/schedule` | готово |
| «4WP Social Manual» (X, LinkedIn): дата плану → `Status_Index` `run_at` | готово |

### 3b. «Already on …?» для кожної мережі
| Крок | Статус |
|---|---|
| Блок «Already on {мережа}?» для всіх готових мереж; «Find by title» — лише YouTube | готово |
| Не-YouTube: URL посту → наявний `POST crosspost/published` | готово |
| Після «Link» queued-задача цієї мережі видаляється | готово |

## Файли
| Файл | Що |
|---|---|
| `views/post-metabox.php` | поле дати; «Already on …?» для всіх мереж |
| `views/post-manual-metabox.php` | поле дати плану |
| `assets/post-metabox.js` | `schedule` у запиті `jobs`; перенесення задачі; дата плану; Link через `crosspost/published` |
| `src/Rest/Rest_Jobs.php` | `schedule` у `POST jobs`; `POST jobs/{id}/schedule`; `POST plan` |
| `crosspost/published` | використано наявний маршрут; `Rest_Link` не змінено |
| `src/Modules/Posting/Posting_Service.php` | `not_before_publish()` (задача 2) |
| `src/Modules/Posting/Status_Index.php` | `plan()`: `run_at_fixed` зберігає ручну дату |
| `src/Modules/Posting/Remote_Link.php` | Link видаляє `queued`-задачу |
| `src/Modules/Posting/Post_Metabox.php` | i18n `dateSaved` |
| `docs/decisions/0005-per-platform-date-and-link.md`, `README` (decisions) | ADR |
| `readme.txt` | changelog 0.6.2 |

## Перевірки
| Перевірка | Результат |
|---|---|
| Задача 2: `not_before_publish(4630)` → 2026-11-18 14:41; опублікований 3505 → зараз | OK (локаль) |
| `php -l`, `composer test` після задачі 2 | OK (81) |
| REST `jobs/111/schedule` → 25.11; на 01.11 → обрізано до дати поста 18.11 | OK (локаль) |
| REST `plan` x.com → 26.11 09:30, після `sync_package` дата лишилась; порожня → скинуто | OK (локаль) |
| `php -l`, `composer test`, phpcs (нових помилок немає) | OK |
| Link не-YouTube (пише meta.json через Drive) | не перевірено |
| UI в редакторі | не перевірено |
