# 0004. Facebook Reels і Instagram Reels через одне підключення Meta
Дата: 2026-11-12 · Статус: Прийнято

## Контекст
Meta-додаток уже створено, тестові користувачі додані. Use case **Instagram API → Unified login** (Facebook Login for Business), є `config_id`. Токен: system-user (SUAT), безстроковий.

## Рішення
- `facebook.com` і `instagram.com` стають API-платформами (`mode: api`). Відео публікуються як Reels.
- Підключення одне: OAuth-провайдер Meta з ключем `facebook.com` (Facebook Login for Business, `config_id`). Адаптер `instagram.com` бере той самий токен і IG-акаунт, прив'язаний до сторінки.
- API: `graph.facebook.com`. Завантаження відео — локальним файлом пакета (`Media_Package::video_path()`) через resumable upload, без публічного URL **(уточнити для IG)**.
- Публікація асинхронна, як у TikTok: `publish()` повертає `processing`, а `check()` опитує статус, доки не отримає `published` або `failed`.

## Наслідки
- У конфігурацію Meta-додатку потрібно додати дозволи сторінки (`pages_show_list`, `pages_manage_posts`, `pages_read_engagement`) **(уточнити)**.
- Redirect URL у Meta: `https://4wp.dev/wp-json/forwp-social/v1/oauth/callback/facebook.com`.
- Instagram Login (`graph.instagram.com`) не використовуємо. Якщо знадобиться — це окремий ADR.
