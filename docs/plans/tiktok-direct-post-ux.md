# План: UX Direct Post TikTok для аудиту (0.7.3)

ADR: [0008](../decisions/0008-tiktok-direct-post-ux.md).

## Кроки

| # | Крок | Статус |
|---|---|---|
| 1 | REST `tiktok/creator` | готово |
| 2 | Панель TikTok у метабоксі + JS-перевірки | готово |
| 3 | Вибір автора в опціях завдання → адаптер | готово |
| 4 | Тести, docs, 0.7.3 | готово |
| 5 | Реліз, перевірка на проді (приватний акаунт) | очікує (автор) |
| 6 | Демо-відео, подання аудиту | очікує (автор) |

## Файли

| Файл | Що |
|---|---|
| `src/Rest/Rest_TikTok.php` (новий), `src/Plugin.php` | endpoint і реєстрація |
| `views/post-metabox.php`, `assets/post-metabox.js`, `assets/admin.css` | панель TikTok |
| `src/Modules/Posting/Post_Metabox.php` | рядки i18n панелі |
| `src/Modules/Posting/Adapters/TikTok_Adapter.php` | `sanitize_options()`, `validate_choice()`, `post_info` з вибору автора |
| `src/Core/Media_Package.php` | поле `tiktok` |
| `src/Modules/Posting/Posting_Service.php` | вибір TikTok в опціях завдання й пакеті |
| `tests/unit/TikTokTest.php` | тести вибору |
| `4wp-social.php`, `readme.txt`, `docs/SPEC.md`, `docs/decisions/*` | 0.7.3, changelog, ADR |

## Перевірки

| Перевірка | Результат |
|---|---|
| `php -l`, `node --check` | ок |
| `phpcs` нових рядків | ок (старі помилки doc-коментарів у файлах лишились) |
| `composer test` | 97/98; падає старий тест іконки Threads |
| Local / прод: панель, публікація SELF_ONLY | |
