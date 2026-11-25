# План: необов'язкові scopes TikTok (0.7.2)

ADR: [0007](../decisions/0007-optional-oauth-scopes.md).

## Кроки

| # | Крок | Статус |
|---|---|---|
| 1 | Обов'язкові / необов'язкові scopes, чекбокси, `requested_scopes()` | готово |
| 2 | «Requested permissions» у кроці 3 | готово |
| 3 | Аналітика: помилка з переліком відсутніх scopes | готово |
| 4 | Версія 0.7.2, changelog, SPEC, ADR | готово |
| 5 | Реліз 0.7.2, composer `*`, staging → прод (автор) | очікує |
| 6 | Подання Display API scopes на рев'ю | очікує |

## Файли

| Файл | Що |
|---|---|
| `src/Auth/TikTok_OAuth_Provider.php` | `REQUIRED_SCOPES`, `OPTIONAL_SCOPES`, `scope_field()`, `requested_scopes()`, чекбокси, текст інструкції |
| `views/partials/tab-accounts.php` | рядок «Requested permissions» |
| `src/Modules/Analytics/Sources/TikTok_Source.php` | `missing_scopes()`, помилка в `fetch()` |
| `4wp-social.php`, `readme.txt` | 0.7.2, changelog |
| `docs/SPEC.md`, `docs/decisions/*` | опис, ADR 0007 |
| сайт `composer.json` | `4wpdev/4wp-social`: `^0.6` → `*` |

## Перевірки

| Перевірка | Результат |
|---|---|
| `php -l` | ок |
| `composer lint` | змінені файли — ок; старі помилки в `Posting_Service.php` (не цей крок) |
| `composer test` | 95/96; падає `test_icons_svg_in_brand_color_and_letters_without_svg` (іконка Threads, не цей крок) |
| Local: чекбокси, «Requested permissions» | |
| Прод: Connect з Live-ключами | |
