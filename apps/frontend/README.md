# Frontend Data/Cache Notes

## SWR and SSR contract

- SSR и CSR должны использовать одинаковые query-параметры для каталога/поиска (`sort_by`, `sort_order`, `per_page`, `attributes[...][]`).
- Все регионозависимые ключи SWR обязаны включать регион (`region_id`), включая home-блоки (`featured/new/sale`) и корзину.
- Для SSR fallback используются ключи из `src/utils/ssr-to-swr.ts`; ручные строковые ключи стоит избегать.

## Cart and counters

- Корзина перезагружается через key-driven SWR (`/api/cart?region_id=...`), без дополнительных listener-дублей.
- Счетчики (`cart/wishlist/compare`) обновляются по фокусу/visibility и на умеренном интервале, чтобы снизить лишнюю сетевую нагрузку.

## Product page

- PDP использует SWR для `stock-settings`, `reviews` (ленивая загрузка по вкладке), `related`.
- Состояние wishlist/compare берется из централизованного хука `useWishlistAndCompare`, без прямых list-запросов со страницы.
