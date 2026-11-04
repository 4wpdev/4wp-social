# 0001. Весь крос-постинг у 4WP Social
Дата: 2026-11-04 · Статус: Прийнято

## Контекст
Крос-постинг у двох плагінах: SEO Helper (manual + розширення + Telegram) і Social (api + черга). Два реєстри платформ, два логи.

## Рішення
Social — єдиний центр (api + manual). CrossPosting із SEO Helper переноситься в Social.

## Наслідки
SEO Helper лишається лише для SEO. Розширення переходить на `forwp-social/v1`.
