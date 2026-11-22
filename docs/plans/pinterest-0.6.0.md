# 0.6.0 — Pinterest

**Статус (2026-11-14):** 0.6.1 на проді. Ланцюжок працює до створення піна; пін блокує Trial access. Продовжити звідси.

## Що зроблено
| Файл | Що |
|---|---|
| `src/Auth/Pinterest_OAuth_Provider.php` | OAuth 2.0 v5: `boards:read`, `boards:write` (0.6.1, без нього пін не створюється), `pins:read`, `pins:write`, `user_accounts:read`; token — Basic auth |
| `src/Api/Pinterest_Client.php` | `user_account`, `boards`, `resolve_board`, `create_pin`, `register_video` (`POST /media`), `upload_video` (multipart на `upload_url`), `media_status` |
| `src/Modules/Posting/Adapters/Pinterest_Adapter.php` | Image pin (base64, синхронно) і video pin (upload → `check()` чекає `succeeded` → pin) |
| `src/Contracts/Image_Capable_Interface.php` | Маркер: адаптер публікує фото без відео |
| `Package_Reader`, `Post_Reader` | Для image-capable відео не обовʼязкове; cover → featured image |
| `Platform_Registry` | Рядок `pinterest.com` (api), одноразово додається до збереженого списку (`forwp_social_platforms_added`) |
| `tab-accounts.php`, `Admin_Menu` | `extra_fields` провайдера: Board, Sandbox |
| `tests/unit/PinterestTest.php` | pin_type, description |

meta.json: `"pin_type": "image" | "video"`, `"board": "<name або id>"`.

## Перевірки
| Перевірка | Результат |
|---|---|
| `php -l`, `composer test` | OK (81) |
| wp-cli: провайдер, адаптер, рядок платформи, image-пакет поста 3505 | OK |
| OAuth connect (прод, акаунт `anatoliydovgun`, BUSINESS) | OK; після Reconnect scope містить `boards:write` |
| `GET /user_account`, `GET /boards` на api.pinterest.com | OK (200) |
| Ті самі запити на api-sandbox.pinterest.com з OAuth-токеном | 401 «Authentication failed.» — sandbox потребує окремого токена |
| Video: `POST /media` → upload → `succeeded` | OK (job 88, 89) |
| `POST /pins` на проді | 403: «Apps with Trial access may not create Pins in production — use API Sandbox» (job 89) |
| Image pin | не перевірено |
| `cover_image_data` для video pin | не перевірено (пін ще не створювався) |

## Далі (рішення автора)
1. **Standard access**: Pinterest Developers → app → Upgrade access (вимоги до демо — уточнити). Після схвалення: «post to the sandbox» вимкнено, Retry — код не міняти.
2. **Sandbox для тесту** (чекає «sandbox» від автора): поле «Sandbox token» у картці Pinterest (`extra_fields`, зберігається в `Credentials_Store`), клієнт бере його для pins/media/boards при `sandbox=1`; якщо в sandbox немає дошок — створити дошку (`POST /boards`).
3. Після першого піна: перевірити image pin (`"pin_type": "image"` у `Videos/01-oop-desktop/meta.json`) і cover для video pin.

Прод: `ssh 4wp@37.27.91.43`, `~/4wp.dev-bedrock/current`, `php ~/bin/wp`. Зараз у Credentials `sandbox` вимкнено.
