# Светофор мебели — market

Монорепозиторий интернет-магазина: frontend, backend/API, Filament-админка и интеграции.

## Быстрый старт

1. Поднять окружение:

   ```bash
   docker compose up -d
   ```

2. Выполнить миграции:

   ```bash
   docker compose exec app php artisan migrate
   ```

3. Запустить базовые сидеры — регионы, доставка, оплата, каталог и роли:

   ```bash
   docker compose exec app bash -c "cd /var/www && ./seed-basic.sh"
   ```

4. Создать или назначить суперадмина:

   ```bash
   # Существующий пользователь — назначить роль
   docker compose exec app php /var/www/assign_super_admin.php admin@example.com

   # Новый пользователь — создать и назначить роль
   docker compose exec app php /var/www/assign_super_admin.php admin@example.com "Super Admin" your_password
   ```

5. Админка локально: `http://localhost:8000/admin_sv/login`.

## Карта документации

Документы по бизнес-подсистемам находятся в `services/backend/docs`. Не дублировать один и тот же контракт в frontend/backend README: для каждой подсистемы должен быть один канонический документ.

### Главная и контент

- [`HOME_SLIDERS.md`](services/backend/docs/HOME_SLIDERS.md) — **канонический контракт слайдеров главной**: Filament, позиции макета, API, SSR/CSR, изображения и демо-данные.
- [`apps/frontend/README.md`](apps/frontend/README.md) — правила frontend data flow, SWR/SSR, корзины и ссылки на канонические backend-контракты.

### Каталог и остатки

- [`INVENTORY_SYSTEM.md`](services/backend/docs/INVENTORY_SYSTEM.md) — остатки и складская модель.
- [`PRODUCT_VARIATIONS.md`](services/backend/docs/PRODUCT_VARIATIONS.md) — вариативные товары.
- [`CRITICAL_ENDPOINTS.md`](services/backend/docs/CRITICAL_ENDPOINTS.md) — критичные API endpoint'ы и поведение.

### Доставка

- [`SHIPPING_SYSTEM.md`](services/backend/docs/SHIPPING_SYSTEM.md) — доставка.
- [`CARRIERS_SYSTEM.md`](services/backend/docs/CARRIERS_SYSTEM.md) — перевозчики.

### Интеграции и события

- [`GATEWAY_PATTERN.md`](services/backend/docs/GATEWAY_PATTERN.md) — gateway/integration pattern.
- [`MAIL_EVENTS_SYSTEM.md`](services/backend/docs/MAIL_EVENTS_SYSTEM.md) — почтовые события.
- [`PAYMENT_SBERBANK_ACQUIRING.md`](services/backend/docs/PAYMENT_SBERBANK_ACQUIRING.md) — эквайринг Сбербанка.

### Frontend / SSR

- [`SSR_README.md`](apps/frontend/SSR_README.md) — устройство SSR.
- [`SSR_PRODUCTION.md`](apps/frontend/SSR_PRODUCTION.md) — production-настройка SSR.
- [`ENV_SETUP.md`](apps/frontend/ENV_SETUP.md) — переменные окружения frontend.

## Правило актуальности документации

Документация описывает либо **реально работающий контракт**, либо явно помеченное **переходное/целевое состояние**. Нельзя описывать запланированное поведение как уже реализованное.

При изменении публичного API, Filament-формы или frontend/backend контракта в том же PR нужно обновить соответствующий канонический документ.

## Региональность и кэш

- Для API каталога и корзины приоритетный параметр локации: `shipping_location_id`; `region_id` используется как legacy fallback.
- Региональная видимость товаров применяется server-side в `GET /api/v1/products` и `GET /api/v1/products/search`, чтобы `total` и пагинация совпадали с фактическим списком.
- При изменении `ProductRegionRule` выполняется инвалидация каталожных cache-tags (`product_index_cache`, `product_search_cache`).
- На frontend SWR-ключи для регионозависимых блоков и корзины должны включать регион.
