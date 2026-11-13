# План: документація 0.5.4 → 0.5.5 «Аналітика» → 0.6.0 Pinterest

## Context
Реліз 0.5.4 (Meta Reels) закрито, тег `v0.5.4` = HEAD. Документація відстає від коду (README/readme.txt без Meta, `Stable tag` 0.5.3, плани без фінальних статусів). Незакомічені зміни черги (чіпи, фільтр після дій, unlink з `platform`) — **йдуть у 0.5.5** (рішення автора). Далі: 0.5.5 — каркас аналітики й нова структура адмінки; паралельно серія матеріалів у хабі `SEO/Analytics/`. 0.6.0 — Pinterest (лише резюме).

## Резюме (як я зрозумів)
- 0.5.5 = «застовбити» аналітику: нова головна сторінка (Dashboard), меню Dashboard · Calendar · Analytics · Settings (Platforms + налаштування — вкладками всередині Settings).
- Джерела з реальними даними на основі наявних підключень: YouTube (YouTube Analytics API), TikTok, Facebook, Instagram. Дані поки просто виводимо (сирий JSON), аналіз — пізніше.
- Google Analytics і LinkedIn — лише заглушки-картки. LinkedIn = ручний імпорт файлу, окреме обговорення.
- Хаб: серія «Analytics API» про аналітику (поєднання, порівняння, підрахунок, прогнози…), структура розділів, без повних текстів.

## Етап A. Нормалізація документації під 0.5.4 (без коду)
| Файл | Що |
|---|---|
| `README.md` | Facebook/Instagram Reels; Layout: `Meta_Client`, `Meta_OAuth_Provider`, адаптери FB/IG; Hooks: `forwp_social_auto_publish`, `forwp_social_series_taxonomies`, `forwp_social_meta_page_id`, `forwp_social_post_media`, `forwp_social_bluesky_service`, `forwp_social_video_schema`; розділ «Queue off production» |
| `readme.txt` | `Stable tag: 0.5.4`; короткий опис і теги + facebook, instagram; External services: Meta Graph API (Terms/Privacy); changelog `= 0.5.4 =` (з коміту 30a5329) і `= 0.5.2 =`, `= 0.5.1 =` (зараз пропущені) |
| `docs/CONCEPT.md` | api-платформи: + Facebook, Instagram |
| `docs/SPEC.md` | Платформи api; новий розділ «Черга»: `auto_publish` лише на production, Run now / Check now / Delete |
| `docs/ABILITIES.md` | звірити список платформ у `publish` |
| `docs/plans/meta-reels.md` | Статус «закрито в 0.5.4», кроки 1, 4, 5 — виконано, таблиця перевірок (з вашим підтвердженням результатів IG) |
| `docs/plans/crossposting-migration.md` | Статуси кроків за кодом (`Manual_Flow`, `Seo_Migration`, `Rest_Crosspost` є) — неперевірене **(уточнити)** |

Перевірка: лише читання/вичитка, `composer lint` не потрібен.

## Етап B. 0.5.5 — Аналітика (каркас)
Документи першими:
- `docs/decisions/0005-admin-structure.md` — меню Dashboard · Calendar · Analytics · Settings; Platforms переходить у Settings (старий slug `forwp-social-platforms` → redirect).
- `docs/decisions/0006-analytics-module.md` — модуль Analytics: джерела, scopes, зберігання сирого JSON, оновлення лише кнопкою (як черга поза продом).
- `docs/plans/analytics-0.5.5.md` — кроки, файли, перевірки. Рядки в `docs/decisions/README.md`.

Код (після «виконайте» на окремий перелік):
| Файли | Що |
|---|---|
| `src/Admin/Admin_Menu.php` | Головна `forwp-social` → Dashboard; submenu Calendar, Analytics, Settings (`forwp-social-settings`, вкладки Platforms · Accounts · Modules · Queue · Documentation) |
| `views/dashboard-page.php` (нов.) | Коротко: статистика місяця (`Calendar::stats()`/`events()`), failed у черзі (`Job_Repository`), підключені акаунти (`OAuth_Controller::providers()`), останнє оновлення аналітики |
| `views/analytics-page.php` (нов.) | Картка на джерело: стан (підключено / бракує scope / заглушка), кнопка «Fetch now», `<pre>` сирого JSON |
| `src/Contracts/Analytics_Source_Interface.php` (нов.) | `slug()`, `label()`, `ready()`, `missing_scopes()`, `fetch(): array\|WP_Error` |
| `src/Modules/Analytics/` (нов.) | `Analytics_Module`, `Source_Registry` (фільтр `forwp_social_analytics_sources`), `Snapshot_Store` (опція `forwp_social_analytics_<slug>`: `data`, `fetched_at`) |
| `src/Modules/Analytics/Sources/` | `YouTube_Source` (YouTube Analytics API v2 `reports`), `TikTok_Source` (`video/list` з метриками), `Facebook_Source` (Page/video insights), `Instagram_Source` (media insights); `Google_Analytics_Source`, `LinkedIn_Source` — заглушки `ready()=false` |
| `src/Auth/Google_OAuth_Provider.php`, `TikTok_OAuth_Provider.php`, `Meta_OAuth_Provider.php` | Додаткові scopes: `yt-analytics.readonly`; `video.list`, `user.info.stats`; `read_insights`, `instagram_manage_insights` — назви звірити з документацією **(уточнити)**; потрібне повторне підключення |
| `src/Rest/Rest_Analytics.php` (нов.) | `GET/POST forwp-social/v1/analytics?source=` (читання знімка / оновлення) |
| `src/Modules/Analytics/Sources/Meta`, `Api/*_Client.php` | Методи читання статистики в наявних клієнтах |
| `tests/` | Тести `Snapshot_Store`, розбір відповіді кожного джерела |
| `assets/admin.css`, `admin.js` | Стилі Dashboard/Analytics |
| Docs | `SPEC.md` (розділ «Аналітика»), README, readme.txt (External services: YouTube Analytics API), changelog 0.5.5 з незакоміченими змінами черги, версія 0.5.5 |

Дії автора у консолях: Google (scope `yt-analytics.readonly`), TikTok (scopes + можливо audit), Meta (дозволи insights).

## Етап C. Хаб `/Users/admin/4wp-publish-hub/SEO/Analytics/` (дія поза репо — окремий дозвіл)
`analytics-wordpress.md` стає хабом серії. Нові заготовки (шапка 4wp-drive + «Planned sections» + «What we already have»):
- `analytics-api.md` — огляд серії: поєднання, порівняння, асоціації, підрахунок, видимість, прогнози
- `woocommerce-analytics-api.md`, `youtube-analytics-api.md`, `tiktok-analytics-api.md`, `meta-insights-api.md`
- `google-analytics-api.md`, `search-console-api.md`, `positions-api.md`
Розділ «Data» у кожному — місце для JSON-прикладів із 4WP Social, заповнимо пізніше.

## 0.6.0 Pinterest — резюме
- У проєкті й хабі згадок про Pinterest немає.
- Загальне **(уточнити за документацією)**: Pinterest API v5, OAuth 2.0; відео-пін = реєстрація медіа (`POST /media`) → завантаження файлу → статус → `POST /pins` з `video_id` у board. Потрібен застосунок із Trial → Standard access. Аналітика пінів: `GET /pins/{id}/analytics` — підійде і для модуля Analytics.
- Вписується в шаблон: `Pinterest_OAuth_Provider` + `Pinterest_Client` + `Pinterest_Adapter` (асинхронно, як TikTok). Окремий ADR перед роботою.

## Команди Git для автора
Після етапу A (документація 0.5.4, без коду черги):
```
git add README.md readme.txt docs/
git commit -m "Docs: normalize for 0.5.4 (Meta Reels, queue off production)"
git push origin main
```
Тег `v0.5.4` не переносимо. Зміни черги лишаються в робочому дереві до 0.5.5. Після релізу 0.5.5:
```
git add -A
git commit -m "Release 0.5.5: Dashboard and Analytics skeleton; queue chips"
git tag -a v0.5.5 -m "4WP Social 0.5.5"
git push origin main --tags
```

## Правило економії токенів
- `AGENTS.md` → «Спілкування з автором»: «Жорстка економія токенів. Усе, що потребує надмірних ресурсів (субагенти, масові читання, довгі пошуки, веб), — лише після узгодження. Без запитань заради запитань».
- Те саме — у пам'ять агента (`memory/token-economy.md` + рядок у `MEMORY.md`).

## Порядок і дозволи
0. Правило економії — разом з етапом A.
1. «Виконайте» на етап A → правки docs → команди git вище.
2. «Виконайте» на ADR 0005/0006 + план 0.5.5 → погодження.
3. «Виконайте» на код 0.5.5 → `php -l`, `composer lint`, `composer test`, ручна перевірка на `4wpdev.local`.
4. Окремий дозвіл на етап C (хаб).

## Перевірка
| Перевірка | Як |
|---|---|
| Синтаксис / стандарти | `php -l`, `composer lint` |
| Тести | `composer test` |
| Меню | `4wpdev.local/wp/wp-admin/admin.php?page=forwp-social` → Dashboard; старий slug Platforms редиректить |
| Аналітика | «Fetch now» для YouTube/TikTok/FB/IG → JSON або зрозуміле «missing scope» |
| Без регресу | Черга, календар, публікація як у 0.5.4 |
