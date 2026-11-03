# Документация «Светофора мебели»

**Единая точка входа.** Сверено с `develop` на 03.11.2026 (SHA `3ad09fc`). Источник истины — реализованный API/сервисы/тесты в конкретной ветке, а не дата документа. **Production может быть старее.**

## С чего начать

| Потребность | Документ |
| --- | --- |
| Что за система и кто за что отвечает | [Архитектура и контракты](system-overview.md) |
| Все документы, включая старые | [Полная инвентаризация с оценкой актуальности](documentation-inventory.md) |
| Что было найдено и исправлено в ходе ревизии | [Аудит 03.11.2026](documentation-audit-20261103.md) |
| Правила поддержки документации | [Регламент](DOCS_POLICY.md) |
| Защита данных, резервные копии, смена провайдера | [Аварийное восстановление](disaster-recovery.md) |
| Запуск Laravel в разработке | [README бэкенда](../services/backend/README.md) |
| Фронтенд и SSR | [README фронтенда](../apps/frontend/README.md); [SSR](../apps/frontend/SSR_README.md) |

## Действующие бизнес-контракты

| Домен | Читайте в первую очередь | Источник для проверки |
| --- | --- | --- |
| Каталог и SKU | [отдельные SKU](catalog-individual-sku-api.md), [комплекты](bundle-multi-warehouse-stock.md) | `ProductController`, `SellableBundle`, `BundlePricing` |
| Цены и акции | [вывод старых правил](retired-product-region-rules.md), [скидки корзины](cart-discounts-and-delivery-estimates.md) | `PricingEngine`, `CartItemResource` |
| Корзина, заказы и отмена | [пилот checkout](pilot-checkout-implementation.md), [складской контракт](warehouse-only-fulfillment.md) | `CartController`, `OrderController`, `AutomaticOrderRefunds` |
| Склады и самовывоз | [складская доставка](warehouse-only-fulfillment.md), [несколько складов](bundle-multi-warehouse-stock.md), [тарифы услуг](warehouse-service-store-cleanup.md) | `WarehouseDeliveryQuote`, `WarehouseServices` |
| Адреса и ГАР | [интеграция адресов](address-directory-frontend-launch.md) | `AddressDirectoryClient`, `DeliveryAddressResolver` |
| Операторы | [заказы в админке](admin-orders-workspace.md) | Filament-ресурсы заказов и платежей |

Основной API: `/api/v1`; фактический перечень определяется `services/backend/routes/api.php`. В этом PR [OpenAPI](../services/backend/public/api-docs/openapi.json) согласована с **93 операциями**; дальнейшую сверку на каждом релизе выполняет [CI-проверка маршрутов](../scripts/check-openapi-routes.mjs).

### Зафиксированные архитектурные решения

1. **Никакого расчёта цен по старым `product_region_rules`**: база SKU + `PricingEngine` + приватные предложения + правила комплекта.
2. **Скидка не вычитается дважды**: `subtotal` уже по текущим ценам; `old_subtotal` и `discount_total` — представление экономии.
3. **Нет записи в календарь доставки**: покупатель не выбирает гарантированные день/интервал; ориентировочные сроки и согласование с менеджером.
4. **Склады — единственный источник исполнения**. Старые API `/api/v1/shipping/calculate`, `/shipping/carriers` и `/regions` не возвращать без отдельного согласованного миграционного проекта.
5. **Адрес здания обязателен для расчёта доставки**. Текстовый fallback может стать заявкой на согласование, но не подтверждённым оплаченным заказом.
6. **Идемпотентность платежей, заказов и резервов** важнее «удобного» повторения запроса после таймаута.

## Исторические документы

Аудиты с датами, старые прототипы ГАР/КЛАДР и ранние схемы тарифов **не удаляем**: они объясняют, почему отказались от решений. Но **не используем как действующее ТЗ**. Ключевые: [исследование старой доставки](shipping-legacy-audit-20261021.md), [отложенное удаление legacy](legacy-shipping-api-removal.md), [аудит склада](warehouse-store-audit-20261026.md), [исходный аудит пилота](pilot-order-readiness-20261026.md), [старый план закрытия бэкенда](backend-completion-plan.md). Полный список — в [реестре](documentation-inventory.md).

## Незавершённые изменения, которые легко принять за готовое

Состояние **на дату ревизии** (проверять заново на GitHub):

- [PR #139](https://github.com/cyrox007/market/pull/139): исправления Сбера и безопасный возврат, **draft**, не часть `develop`.
- [PR #140](https://github.com/cyrox007/market/pull/140): резервная выдача кэша адресов при outage, **open**, не часть `develop`.

Даже после merge нельзя утверждать «работает на боевом» без проверки развёрнутого SHA, конфигурации и сквозных сценариев.

## Для внесения изменений

При изменении API, цены, заказа, банка, адресов или склада обновлять **одновременно** код, тесты, исходный контракт и ссылки из этого каталога. Новые датированные результаты аудита помещать под исторические, а не объявлять автоматически истиной. Правила — [DOCS_POLICY.md](DOCS_POLICY.md).
