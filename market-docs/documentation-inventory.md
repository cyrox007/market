# Полный реестр документации репозитория

> **Инвентаризация:** 03.11.2026, исходный `develop` = `3ad09fc7046a65e8102e57f36707102a5aa41831`. В исходном дереве **76 Markdown/RST/Asciidoc-файлов** (дополнительно `robots.txt` — технический, не инструкция). Этот PR добавляет новые нормативные документы. Индекс файлов исходного дерева ниже сгенерирован из Git-дерева, без догадок по именам файлов.
>
> **Статус «ОСНОВА»** — ориентир по текущему архитектурному контракту, но при конфликте с кодом приоритет у кода. **«НЕ ИСПОЛЬЗОВАТЬ»** — документ содержит obsolete API, устаревшие шаги или опасные ручные действия. **«ИСТОРИЯ»** — отчёт о состоянии на дату/ветку, не сегодняшняя готовность. **«ПРОВЕРИТЬ»** — ещё нельзя считать нормативным без точечной сверки содержимого с живым кодом.
>
> Из них в исходном дереве: НЕ ИСПОЛЬЗОВАТЬ — **13**, ОСНОВА — **10**, ПРОВЕРИТЬ — **39**, ИСТОРИЯ — **14**. Это **классификация документов**, а не процент проаудированных строк: часть специализированных инструкций намеренно помечена для последующей технической сверки.

## Навигация по зонам

### Корневые документы и утилиты

| Файл | Статус | Пояснение |
| --- | --- | --- |
| [`API_DEBUG_EXAMPLES.md`](../API_DEBUG_EXAMPLES.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`DEBUG_VARIANT_ISSUE.md`](../DEBUG_VARIANT_ISSUE.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`PM2_README.md`](../PM2_README.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`README.md`](../README.md) | **ОСНОВА** | заменён в текущем PR на навигацию по живой системе |
| [`tools/k6/README.md`](../tools/k6/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |

### Концепция и решения магазина (`market-docs/`)

| Файл | Статус | Пояснение |
| --- | --- | --- |
| [`market-docs/address-directory-frontend-launch.md`](address-directory-frontend-launch.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/address-geolocation-contract.md`](address-geolocation-contract.md) | **ПРОВЕРИТЬ** | частично устарел; детали выбирать по более позднему address-directory-frontend-launch |
| [`market-docs/address-launch-readiness.md`](address-launch-readiness.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/admin-orders-workspace.md`](admin-orders-workspace.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/admin-workspace-continuity.md`](admin-workspace-continuity.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/backend-completion-plan.md`](backend-completion-plan.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/bulk-product-recommendations.md`](bulk-product-recommendations.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/bundle-multi-warehouse-stock.md`](bundle-multi-warehouse-stock.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/cart-discounts-and-delivery-estimates.md`](cart-discounts-and-delivery-estimates.md) | **ОСНОВА** | поля старых цен и запрет бронирования сверены с API |
| [`market-docs/catalog-dimensions-facets.md`](catalog-dimensions-facets.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/catalog-individual-sku-api.md`](catalog-individual-sku-api.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/catalog-quick-fixes.md`](catalog-quick-fixes.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/catalog-transfer-local.md`](catalog-transfer-local.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/collection-wide-recommendations.md`](collection-wide-recommendations.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/customer-locality.md`](customer-locality.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/frontend-fulfillment-contract.md`](frontend-fulfillment-contract.md) | **ПРОВЕРИТЬ** | содержит следы старых региональных правил; актуальные цены/доставку брать из новых контрактов |
| [`market-docs/gar-coverage-audit-20261009.md`](gar-coverage-audit-20261009.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/gar-pilot.md`](gar-pilot.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/legacy-shipping-api-removal.md`](legacy-shipping-api-removal.md) | **НЕ ИСПОЛЬЗОВАТЬ** | отложенное удаление описано до warehouse-only; старый контракт больше не использовать |
| [`market-docs/local-store-api.md`](local-store-api.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/offer-content-inheritance.md`](offer-content-inheritance.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/pilot-algorithm-audit-20261026.md`](pilot-algorithm-audit-20261026.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/pilot-checkout-implementation.md`](pilot-checkout-implementation.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/pilot-order-readiness-20261026.md`](pilot-order-readiness-20261026.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/pricing-checkout-simplification.md`](pricing-checkout-simplification.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/product-description-editor.md`](product-description-editor.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/product-manufacturer.md`](product-manufacturer.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/public-auth-controls.md`](public-auth-controls.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/retired-product-region-rules.md`](retired-product-region-rules.md) | **ОСНОВА** | источник цены PricingEngine, не product_region_rules |
| [`market-docs/shipping-legacy-audit-20261021.md`](shipping-legacy-audit-20261021.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/store-warehouse-sites.md`](store-warehouse-sites.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/visual-price-rules-local.md`](visual-price-rules-local.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/warehouse-addresses.md`](warehouse-addresses.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`market-docs/warehouse-coordinates-local.md`](warehouse-coordinates-local.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |
| [`market-docs/warehouse-delivery-quote.md`](warehouse-delivery-quote.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`market-docs/warehouse-only-fulfillment.md`](warehouse-only-fulfillment.md) | **ОСНОВА** | маршруты склада сверены с routes/api.php |
| [`market-docs/warehouse-service-store-cleanup.md`](warehouse-service-store-cleanup.md) | **ОСНОВА** | Основной документ своего модуля |
| [`market-docs/warehouse-service-tariffs.md`](warehouse-service-tariffs.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`market-docs/warehouse-store-audit-20261026.md`](warehouse-store-audit-20261026.md) | **ИСТОРИЯ** | Снимок старого этапа — не руководство внедрения |

### Бэкенд и интеграции (`services/backend/`)

| Файл | Статус | Пояснение |
| --- | --- | --- |
| [`services/backend/API_DOCS_README.md`](../services/backend/API_DOCS_README.md) | **ПРОВЕРИТЬ** | список эндпоинтов/полноту OpenAPI перепроверить по routes/api.php |
| [`services/backend/CACHE_INTEGRATION.md`](../services/backend/CACHE_INTEGRATION.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/CACHE_OPTIMIZATION_GUIDE.md`](../services/backend/CACHE_OPTIMIZATION_GUIDE.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/FIX_PAYMENT_METHODS.md`](../services/backend/FIX_PAYMENT_METHODS.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`services/backend/PRODUCT_BLOCKS_EXTENSIBILITY.md`](../services/backend/PRODUCT_BLOCKS_EXTENSIBILITY.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/README.md`](../services/backend/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/ROLES_PERMISSIONS_SETUP.md`](../services/backend/ROLES_PERMISSIONS_SETUP.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`services/backend/ROLES_PERMISSIONS_SUMMARY.md`](../services/backend/ROLES_PERMISSIONS_SUMMARY.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/ROLES_SETUP_COMPLETE.md`](../services/backend/ROLES_SETUP_COMPLETE.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/SEO_SETUP.md`](../services/backend/SEO_SETUP.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/TEST_ORDERS_API.md`](../services/backend/TEST_ORDERS_API.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`services/backend/app/Services/Catalog/Integrations/OpenCart/OPENCART_VARIANTS_ANALYSIS.md`](../services/backend/app/Services/Catalog/Integrations/OpenCart/OPENCART_VARIANTS_ANALYSIS.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/app/Services/Catalog/Integrations/README.md`](../services/backend/app/Services/Catalog/Integrations/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/app/Services/Inventory/Integrations/README.md`](../services/backend/app/Services/Inventory/Integrations/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/database/seeders/SEEDERS_GUIDE.md`](../services/backend/database/seeders/SEEDERS_GUIDE.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/database/seeders/data/README.md`](../services/backend/database/seeders/data/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/docs/CARRIERS_SYSTEM.md`](../services/backend/docs/CARRIERS_SYSTEM.md) | **НЕ ИСПОЛЬЗОВАТЬ** | описана удалённая система carriers / shipping-methods |
| [`services/backend/docs/CRITICAL_ENDPOINTS.md`](../services/backend/docs/CRITICAL_ENDPOINTS.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`services/backend/docs/GATEWAY_PATTERN.md`](../services/backend/docs/GATEWAY_PATTERN.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/docs/HOME_SLIDERS.md`](../services/backend/docs/HOME_SLIDERS.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/docs/INVENTORY_SYSTEM.md`](../services/backend/docs/INVENTORY_SYSTEM.md) | **ПРОВЕРИТЬ** | Выявлена устаревшая схема 1С/Vanilo и неверный образец заказа; добавлен проверенный по коду действующий путь синхронизации. Старые примеры не применять, остальное требует дополнительной сверки |
| [`services/backend/docs/MAIL_EVENTS_SYSTEM.md`](../services/backend/docs/MAIL_EVENTS_SYSTEM.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/docs/PAYMENT_SBERBANK_ACQUIRING.md`](../services/backend/docs/PAYMENT_SBERBANK_ACQUIRING.md) | **ПРОВЕРИТЬ** | проверить настройку мерчанта; изменения в незавершённом PR #139 не считать выпущенными; заменить пароль-пример |
| [`services/backend/docs/PRODUCT_VARIATIONS.md`](../services/backend/docs/PRODUCT_VARIATIONS.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`services/backend/docs/SHIPPING_SYSTEM.md`](../services/backend/docs/SHIPPING_SYSTEM.md) | **НЕ ИСПОЛЬЗОВАТЬ** | описана удалённая система тарифов по shipping_locations |

### Фронтенд (`apps/frontend/`)

| Файл | Статус | Пояснение |
| --- | --- | --- |
| [`apps/frontend/ENV_SETUP.md`](../apps/frontend/ENV_SETUP.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`apps/frontend/NGINX_CONFIG.md`](../apps/frontend/NGINX_CONFIG.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`apps/frontend/README.md`](../apps/frontend/README.md) | **ПРОВЕРИТЬ** | схема SWR/SSR; редактирует отдельный фронтенд-разработчик |
| [`apps/frontend/SSR_MIGRATION_GUIDE.md`](../apps/frontend/SSR_MIGRATION_GUIDE.md) | **НЕ ИСПОЛЬЗОВАТЬ** | Не применять команды/контракт без перепроверки |
| [`apps/frontend/SSR_PRODUCTION.md`](../apps/frontend/SSR_PRODUCTION.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`apps/frontend/SSR_README.md`](../apps/frontend/SSR_README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |
| [`apps/frontend/src/lib/README.md`](../apps/frontend/src/lib/README.md) | **ПРОВЕРИТЬ** | Точечно сверить контракт с кодом и тестами |

## Новые документы этого PR

- [Каталог и входная точка](README.md) — куда идти за нужной инструкцией.
- [Система и границы ответственности](system-overview.md) — минимальный контракт, сверенный с `develop`.
- [Восстановление и независимость от хостинга](disaster-recovery.md) — операционный план, не декларация готовности.
- [Политика актуализации](DOCS_POLICY.md) — владельцы, статусы, ревью, запрет опасных примеров.

## Открытые изменения, не являющиеся частью develop

На 03.11.2026 в момент аудита: [PR #139](https://github.com/cyrox007/market/pull/139) — исправления банковского цикла Сбера, **draft**; [PR #140](https://github.com/cyrox007/market/pull/140) — расширенное аварийное кэширование адресов, готово к review, **ещё не слито**. Никакая документация не должна называть их функциями production до проверки релиза.

## Верификация и ограничения

- Пройденный CI подтверждает тесты конкретного коммита, но **не** доступность Beget, данных в production, ключей эквайринга или возможность восстановления.
- Для внешнего адресного API требуется отдельная проверка статуса и покрытия.
- Некоторые файлы исторически содержали реальные/похожие на реальные значения секретов. Не переносить их в новые документы; активные секреты нужно вращать отдельно.
