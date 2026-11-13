# 0.6.0 — Pinterest

## Що зроблено
| Файл | Що |
|---|---|
| `src/Auth/Pinterest_OAuth_Provider.php` | OAuth 2.0 v5: `boards:read`, `pins:read`, `pins:write`, `user_accounts:read`; token — Basic auth |
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
| OAuth connect, image pin, video pin | (уточнити) — автор |
| Sandbox для Trial access: той самий токен на api-sandbox | (уточнити за документацією Pinterest) |
| `cover_image_data` / `cover_image_key_frame_time` для video pin | (уточнити) |
