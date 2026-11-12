# План: Facebook Reels і Instagram Reels

Дата: 2026-11-12 · Рішення: [ADR 0004](../decisions/0004-meta-reels.md). Коміти робить автор.

Кожен крок 2–5 стартує тільки після «виконайте» автора на погоджений перелік файлів.

| Крок | Що | Статус |
|---|---|---|
| 0 | Флоу в проєкті: `AGENTS.md`, `CLAUDE.md`, `.githooks/pre-commit`, ADR 0004, цей план | виконано |
| 1 | Налаштування Meta-додатку (автор) | очікує |
| 2 | Підключення Meta (OAuth): `Meta_OAuth_Provider`, поле Configuration ID | виконано: сторінка 4wp.dev, дозволи `pages_*` |
| 3 | Facebook Reels: `Meta_Client`, `Facebook_Reels_Adapter`, тест `FacebookReelsTest` | виконано: пост 3143 → https://www.facebook.com/reel/908321638936658/ (2026-11-12) |
| 4 | Instagram Reels: `Instagram_Reels_Adapter`, IG-методи `Meta_Client`, тест `InstagramReelsTest`; IG @adovgun прив'язаний до сторінки 4wp.dev | код готовий, перевірка публікації |
| 4a | Черга: кнопки **Run now** і **Delete** для `queued` / `failed` (`Admin_Menu`, `Job_Repository::delete()`, `tab-queue.php`) | код готовий |
| 4b | Захист поза продом: `Queue::auto_publish()` (лише `production`, фільтр `forwp_social_auto_publish`); інакше черга сама нічого не шле, лише **Run now** / **Check now** на одній задачі; банер у черзі. Локально `DISABLE_WP_CRON=true` у `.env` 4wp.dev | код готовий |
| 5 | Документація й реліз 0.6.0 | — |

## Крок 1. Meta-додаток (автор)
Є зараз: Instagram API → Unified login, `config_id` 1700391961649040, SUAT (never expires), `instagram_basic`, `instagram_content_publish`.

- [ ] Redirect URL: додати `https://4wp.dev/wp-json/forwp-social/v1/oauth/callback/facebook.com`. Зараз зареєстровано `admin.php?page=4wp-social`, з ним авторизація не пройде.
- [ ] Дозволи для Facebook Reels: `pages_show_list`, `pages_manage_posts`, `pages_read_engagement` **(уточнити: чи можна додати їх у цю конфігурацію)**.
- [ ] Тестовий IG-акаунт прийняв запрошення (Generate access tokens → Add account).
- [ ] IG Business/Creator прив'язаний до FB-сторінки.

## Крок 2. Підключення Meta
| Файли | Що |
|---|---|
| `src/Auth/Meta_OAuth_Provider.php` | `OAuth_Provider_Interface` за зразком `TikTok_OAuth_Provider`: auth URL з `config_id`; обмін code на токен; для user-токена — long-lived токен і токен сторінки |
| `src/Auth/OAuth_Controller.php` | Реєстрація провайдера `facebook.com` |
| `views/` (Accounts) | Поле `config_id`, вибір сторінки та IG-акаунта, setup guide |

## Крок 3. Facebook Reels
| Файли | Що |
|---|---|
| `src/Api/Meta_Client.php` | `/{page-id}/video_reels` start → upload (`rupload.facebook.com`) → finish → статус |
| `src/Modules/Posting/Adapters/Facebook_Reels_Adapter.php` | `publish()` → `processing(video_id)`, `check()` → `published` / `failed` |
| `src/Modules/Posting/Adapter_Registry.php`, `Platform_Registry.php` | Конектор для `facebook.com` |

## Крок 4. Instagram Reels
| Файли | Що |
|---|---|
| `src/Api/Meta_Client.php` | `/{ig-user-id}/media` (`media_type=REELS`, resumable upload **(уточнити)**) → `status_code` → `media_publish` |
| `src/Modules/Posting/Adapters/Instagram_Reels_Adapter.php` | Асинхронний шаблон; caption — як `TikTok_Adapter::caption()`, ліміт 2200 |
| `Platform_Registry.php`, `Platform_Icons.php` | `instagram.com` у реєстрі, іконка |

## Крок 5. Документація й реліз
`docs/SPEC.md`, `README.md`, `readme.txt`, версія 0.6.0, changelog.

## Перевірка
| Перевірка | Результат |
|---|---|
| `php -l`, `composer lint` | — |
| `composer test` (тести caption / check) | — |
| `4wpdev.local`: підключення Meta тестовим користувачем | — |
| Пакет із відео → FB: `processing` → `published`, посилання в «Social» і календарі | — |
| Пакет із відео → IG: те саме | — |
| Помилки: без відео, прострочений токен, IG без сторінки | — |
