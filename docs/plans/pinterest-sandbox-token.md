# 0.6.2 — Pinterest sandbox token

**Мета:** з Trial access створити пін у sandbox і зняти відео для заявки на Standard access.

**Причина (перевірено на проді 2026-11-17):** OAuth-токен на `api.pinterest.com` → 200, на `api-sandbox.pinterest.com` → 401 «Authentication failed.». Sandbox приймає лише токен із Pinterest Developers → app → Configure → Generate access tokens → Sandbox (діє 30 днів).

## Кроки
| # | Крок | Статус |
|---|---|---|
| 1 | Поле `sandbox_token` (password) у картці Pinterest | готово |
| 2 | Адаптер: при `sandbox=1` клієнт бере `sandbox_token` | готово |
| 3 | Версія 0.6.2, changelog | готово |
| 4 | Ручна перевірка на `4wpdev.local` | автор |
| 5 | Деплой, токен, Retry на проді | автор |
| 6 | Sandbox без дошок: `resolve_board` створює дошку (`POST /boards`, назва з налаштувань або «4WP.dev») | готово |
| 7 | Відео процесу → заявка на Standard access → вимкнути sandbox | автор |

## Файли
| Файл | Що |
|---|---|
| `src/Auth/Pinterest_OAuth_Provider.php` | `extra_fields.sandbox_token`, тип `password` |
| `views/partials/tab-accounts.php` | Поле `password`: значення не виводиться, placeholder «Saved» |
| `src/Admin/Admin_Menu.php` | Порожнє `password`-поле зберігає попереднє значення |
| `src/Modules/Posting/Adapters/Pinterest_Adapter.php` | `client()`: sandbox → `sandbox_token`, порожній → `WP_Error` |
| `src/Api/Pinterest_Client.php` | `resolve_board`: у sandbox створює дошку, якщо її немає |
| `4wp-social.php`, `readme.txt` | 0.6.2, changelog |

## Перевірки
| Перевірка | Результат |
|---|---|
| `php -l` змінених файлів | OK |
| `composer test` | OK (81) |
| `phpcs` змінених файлів | нових помилок немає (наявні — старі doc-comment) |
| Ручна: поле в адмінці, збереження, порожнє не стирає | не перевірено |
| Локаль: sandbox-токен, `GET /user_account`, `GET /boards` | 200; дошок 0 |
| Пін у sandbox | не перевірено |
