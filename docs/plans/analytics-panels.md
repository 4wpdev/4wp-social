# 0.7.0 — Спільна панель аналітики і щоденні метрики

ADR: `docs/decisions/0006-analytics-daily-store.md`.

## Кроки
| Крок | Статус |
|---|---|
| Таблиця `forwp_social_metrics`, `DB_VERSION` 3 | готово |
| `Analytics_Daily_Interface`, `Metrics_Store` (collect, rows, items з кешем, range, totals, delta) | готово |
| YouTube: `daily()`, `items()`, метрики; `fetch()` лише підсумки каналу | готово |
| Header: вкладки + період + Fetch now праворуч; період зберігається після Fetch now | готово |
| Плитки з ▲/▼ проти попереднього періоду | готово |
| Діаграма: tooltip, клік по дню → топ-5 (REST `analytics/items`) | готово |
| Таблиця: сортування по всіх колонках, за замовчуванням дата публікації ↓ | готово |
| Правки: підсумки каналу в одному ряду з періодом; статистика дня перед топ-5; посилання на сторінку сайту (іконка CPT + шлях) під назвою відео (`Status_Index::posts_by_remote()`) | готово |
| Фокус-слова сторінки в таблиці (семантичне ядро) | пізніше |
| GA4 мінімум: вкладка Google Analytics на панелі — перегляди/користувачі/сесії/залучення по днях (історія 365), топ сторінок, канали і джерела трафіку; Property ID на сторінці; scope `analytics.readonly` | готово |
| GA4 по сторінці сайту в таблицях відео | пізніше |
| LinkedIn: імпорт `.xlsx` (Aggregate analytics) — `Xlsx_Reader`; дні (покази, взаємодії, нові підписники) у `forwp_social_metrics`, пости в опції `forwp_social_linkedin_posts`, демографія в знімку; панель для ручних джерел | готово |
| TikTok на панель: щоденний знімок загальних сум (`tiktok.com:raw`) → приріст за вчора; відео за період = різниця знімків (`Analytics_Cumulative_Interface`) | готово |
| Facebook на панель: insights сторінки по днях з API (історія 365 днів, частинами по 90); Reels — щоденні знімки (`facebook.com:raw`), приріст рахунку не перезаписує історію (`history_days() > 1`) | готово |
| Instagram на панель: охоплення (reach) по днях з API (лише воно має денну серію; частинами по 30 днів, історія 365); пости — щоденні знімки (`instagram.com:raw`) | готово |

## Файли
| Файл | Що |
|---|---|
| `src/Contracts/Analytics_Daily_Interface.php` | новий контракт |
| `src/Modules/Analytics/Metrics_Store.php` | таблиця, collect, періоди, підсумки |
| `src/Database/Schema.php` | таблиця метрик, `DB_VERSION` 3 |
| `src/Modules/Analytics/Analytics.php` | collect у `refresh()` (cron і Fetch now) |
| `src/Modules/Analytics/Sources/YouTube_Source.php` | реалізація інтерфейсу |
| `src/Rest/Rest_Analytics.php` | `GET analytics/items` |
| `src/Admin/Admin_Menu.php` | `assets/analytics.js`, період після Fetch now |
| `views/analytics-page.php` | спільний header |
| `views/partials/analytics-panel.php` | спільна панель |
| `views/partials/analytics-youtube.php` | лише підсумки каналу |
| `assets/analytics.js`, `assets/admin.css` | tooltip, топ дня, сортування, стилі |
| `tests/unit/MetricsStoreTest.php` | тести |
| `src/Contracts/Analytics_Cumulative_Interface.php` | джерела з загальними сумами (TikTok) |
| `src/Modules/Analytics/Sources/TikTok_Source.php`, `views/partials/analytics-tiktok.php` | TikTok на панелі |
| `src/Modules/Analytics/Sources/Facebook_Source.php`, `views/partials/analytics-facebook.php` | Facebook на панелі |
| `src/Modules/Analytics/Sources/Instagram_Source.php`, `views/partials/analytics-instagram.php` | Instagram на панелі |
| `src/Modules/Analytics/Xlsx_Reader.php`, `src/Modules/Analytics/Sources/LinkedIn_Source.php`, `views/partials/analytics-linkedin.php`, `src/Rest/Rest_Analytics.php`, `assets/admin.css`, `tests/unit/LinkedInImportTest.php` | LinkedIn |
| `src/Modules/Analytics/Sources/Google_Analytics_Source.php`, `views/partials/analytics-google.php`, `src/Auth/Google_OAuth_Provider.php`, `src/Admin/Admin_Menu.php`, `views/analytics-page.php` | GA4 |
| `4wp-social.php`, `readme.txt`, `README.md`, `docs/SPEC.md`, ADR | версія 0.7.0, документація |

## Перевірки
| Перевірка | Результат |
|---|---|
| `php -l` змінених файлів | OK |
| `phpunit` `MetricsStoreTest` | OK (8) |
| `phpunit` усі | 88/89: падає `CrosspostTest::test_icons_svg_in_brand_color_and_letters_without_svg` (іконка Threads, не стосується змін) |
| phpcs нових файлів | OK; `Admin_Menu.php` — старі зауваження до doc-коментарів |
| Локаль: міграція → `db v3`, collect YouTube → 363 дні (2025-11-22 … 2026-11-19) | OK |
| Локаль: топ-5 за 2026-11-19 → 5; відео за 28 днів → 56 | OK |
| Локаль: рендер сторінки 28d / 7d / prev_month без помилок | OK |
| Правки: рендер — 1 ряд груп, 32 посилання на сайт, статистика для 28 днів | OK (локаль) |
| TikTok локаль: знімок 1 акаунт + 40 відео; панель з періодом, підсумки акаунта в першому ряду; приріст — з наступного збору | OK |
| `phpunit` після TikTok | 90/91 (та сама стара помилка Threads) |
| Facebook локаль: знімок 15 Reels; insights порожні без `read_insights` → «No daily data» + підказка; рендер без помилок | OK |
| `phpunit` після Facebook | 91/92 (та сама стара помилка Threads) |
| Instagram локаль: 364 дні reach (2025-11-22 … 2026-11-20), знімок 18 постів; рендер 7d — 7 стовпчиків | OK |
| `phpunit` після Instagram | 92/93 (та сама стара помилка Threads) |
| GA4 локаль: вкладка з полем Property ID, рендер без помилок; дані — після scope і Reconnect | OK (частково) |
| `phpunit` після GA4 | 93/94 (та сама стара помилка Threads) |
| LinkedIn локаль: імпорт файлу 2026-08-25…2026-11-22 → 90 днів, 44 пости; рендер 28d — 28 стовпчиків, 12 постів, демографія | OK |
| `phpunit` після LinkedIn | 95/96 (та сама стара помилка Threads) |
| UI в браузері (tooltip, клік, сортування) | перевіряє автор |
