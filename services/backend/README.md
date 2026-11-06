# Backend API / Admin — Светофор мебели

**Laravel 12 + PHP 8.2+, Filament 4, Vanilo 5.1.** Это backend интернет-магазина, а не чистый шаблон Laravel.

Полная [карта проекта](../../market-docs/system-overview.md) · [единая документация](../../market-docs/README.md) · [аварийное восстановление](../../market-docs/disaster-recovery.md) · [реестр старых документов](../../market-docs/documentation-inventory.md).

## Важные каталоги

| Путь | Назначение |
| --- | --- |
| `routes/api.php` | Публичное API `/api/v1`, актуальные маршруты |
| `app/Http/Controllers/Api/` | Валидация, ответы каталога, корзины, заказов и адресов |
| `app/Services/Pricing/` | Ценовые политики, персональные цены |
| `app/Services/Product/` | Товары, предложения, продаваемые комплекты |
| `app/Services/Inventory/` | Фактические складские остатки, синхронизация с 1С |
| `app/Services/Shipping/` | Складские тарифы, доставка, самовывоз, услуги |
| `app/Services/Address/` | Адресный справочник и разрешение здания ГАР/КЛАДР |
| `app/Payment/`, `app/Jobs/` | Банковские шлюзы, подтверждения, фоновые задания |
| `app/Models/Order/` | Заказы и статусы |
| `routes/console.php` | Планировщик фоновых задач и обработчиков |
| `tests/` | Unit/feature и сквозные регрессии |
| `public/api-docs/openapi.json` | Спецификация; сравнивать с реальными маршрутами |

## Локальный запуск

Рекомендуемый путь — через корневой `docker-compose.yml`:

```bash
# В корне репозитория:
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Для разработки без Docker выполнять команды **из services/backend**, а не из корня:

```bash
cd services/backend
composer install
cp .env.example .env
php artisan key:generate
# Настроить локальную БД и окружение, затем:
php artisan migrate
php artisan test
```

Окружение production использует отдельные значения для БД, очередей, доступа к 1С, банков, SMTP и адресного API. **Не копировать локальные секреты из Docker в production.** Не выполнять миграции, сидеры, очистку БД и платежные операции на боевом сервере без отката и проверенной резервной копии.

## Что представляет собой актуальный API

- **Каталог и SKU:** `/products`, `/products/{slug}`, `/products/search`.
- **Корзина:** `/cart`, добавление, установка количества, удаление; SKU определяет бэкенд.
- **Адреса:** `/address-directory/status`, `/address-directory/{level}`, `/address-directory/hierarchy/{externalId}`.
- **Доставка/самовывоз:** `POST /shipping/quote`, `POST /shipping/pickup-options`. Старые API shipping_locations/carriers/calculate из архивных инструкций не являются активным контрактом.
- **Оформление:** `POST /orders`, а `POST /orders/one-click` — подготовка прямой покупки, **не созданный заказ**.
- **Цены:** `PricingEngine`, а не архивные `product_region_rules`.
- **Скидки корзины:** `subtotal` уже актуален; `old_subtotal`/`discount_total` — сравнение.
- **Доставка:** точный календарь свободных дней и временных слотов не поддерживается; `delivery_date`/`delivery_time` нельзя передавать от клиента.

Детали и ограничения: [актуальный складской контракт](../../market-docs/warehouse-only-fulfillment.md), [цены и скидки](../../market-docs/cart-discounts-and-delivery-estimates.md), [адреса](../../market-docs/address-directory-frontend-launch.md), [действие заказов](../../market-docs/pilot-checkout-implementation.md).

## Запуск проверок

```bash
cd services/backend
composer test
# Или из корня с Docker:
# docker compose exec app php artisan test
```

Проходящие тесты конкретной ветки **не подтверждают** наличие банковских реквизитов, новых миграций или доступность production/Beget. Последовательность релиза и аварийного восстановления должна быть проверена отдельно. Нельзя объявлять изменения незакрытого PR выпущенными в production.

## Эксплуатационная безопасность

Никогда не повторять запрос оплаты или возврата после таймаута без независимой сверки банка. При восстановлении бэкапа сверять заказы, платежи и резервы с 1С. Секреты не размещать в Git, документах и логах. Если в старой инструкции виден «пример» реального пароля — считать его потенциально скомпрометированным до подтверждения обратного.

Старые руководства `docs/SHIPPING_SYSTEM.md`, `docs/CARRIERS_SYSTEM.md` и `TEST_ORDERS_API.md` являются историческими и **не должны** служить инструкцией внедрения нового checkout. Вся документация классифицирована в [реестре](../../market-docs/documentation-inventory.md).
