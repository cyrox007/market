# Интеграции каталога

В этой папке находятся реализации **импорта и экспорта каталога** (категории и товары) из внешних систем. Архитектура построена по аналогии с модулями экспорта/импорта 1С–Битрикс: контракты задают интерфейс, абстрактные классы — общую логику, конкретные классы — работу с конкретным API.

## Схема

- **Контракт:** `App\Services\Catalog\Contracts\CatalogImportInterface` — метод `import(array $options): CatalogImportResult`.
- **Базовый класс:** `App\Services\Catalog\AbstractCatalogImport` — шаблонный процесс: загрузка → маппинг → сохранение категорий и товаров.
- **Реализации** в этой папке подключаются к внешнему API и переопределяют методы загрузки и маппинга.

## Доступные интеграции

### Светофор 1C

- **Класс:** `Svetofor1CCatalogImport`
- **Назначение:** импорт категорий, товаров, торговых предложений (модификаций) и остатков из API Светофор (1C).
- **Источник (эндпоинты API, см. [документацию](http://api.svetofor-mebel.ru/api/docs)):**
  - Товары (инкрементально): `GET {base_url}/api/v1/integration/1c/v2/cache/products?updatedAfter=...`
  - Характеристики товара: `GET {base_url}/api/v1/integration/1c/v2/cache/products/{externalId}/characteristics`
  - Цена товара: `GET {base_url}/api/v1/integration/1c/v2/cache/products/{externalId}/price`
  - Склады товара: `GET {base_url}/api/v1/integration/1c/v2/cache/products/{externalId}/stocks`
  - Модификации товара (торговые предложения): `GET {base_url}/api/v1/integration/1c/products/{externalId}/modifications`
  - Отправка остатков обратно в 1С: `POST {base_url}/api/v1/integration/1c/v2/cache/stocks` (через action после изменения остатков на сайте)
  - Остатки (legacy bulk, опционально): путь задаётся в конфиге `stock_path`.
- **Порядок обновления каталога:** товары из cache (`updatedAfter`) → модификации → характеристики → цена → остатки по складам.
- **Минутный синк (cron + `SyncProductAndStocksFrom1CJob`):** для каждого изменённого `externalId` — `GET .../price` и `GET .../stocks` (остатки по складам).
- **Watermark updatedAfter:** берётся время последнего успешного запуска импортера (`catalog_import_runs.finished_at`), с fallback на `SVETOFOR_1C_INITIAL_UPDATED_AFTER` или `now - SVETOFOR_1C_UPDATED_AFTER_FALLBACK_MINUTES`.
- **Конфиг:** `config/catalog_import.php` → секция `config.svetofor_1c`. Переменные окружения:
  - `SVETOFOR_CATALOG_API_BASE_URL` — базовый URL API (по умолчанию `http://api.svetofor-mebel.ru`);
  - `SVETOFOR_CATALOG_API_KEY` — ключ авторизации (если нужен);
  - `SVETOFOR_CATALOG_API_TIMEOUT` — таймаут запросов в секундах (по умолчанию 60);
  - `SVETOFOR_1C_PRODUCTS_CACHE_PATH` — путь к cache endpoint товаров;
  - `SVETOFOR_1C_INITIAL_UPDATED_AFTER` — начальный watermark для первого запуска;
  - `SVETOFOR_1C_UPDATED_AFTER_FALLBACK_MINUTES` — fallback-окно в минутах, если предыдущих успешных запусков нет;
  - `SVETOFOR_1C_STOCK_SYNC_PATH` — путь для `POST` синхронизации остатков обратно в 1С;
  - `SVETOFOR_1C_STOCK_PATH` — legacy bulk-эндпоинт остатков (пусто = не вызывать).
- **Запуск:** админка Filament → группа «Интеграции» → «Синхронизация каталога» → кнопка «Поставить в очередь». Импорт выполняется в фоне через очередь Laravel (Job `RunCatalogImportJob`). Результаты последнего запуска отображаются под каждым провайдером. Для обработки очереди должен быть запущен воркер: `php artisan queue:work` (или отдельная очередь `catalog_import` через `CATALOG_IMPORT_QUEUE` в .env). Доступ по разрешению `viewAny catalog_sync` (роли admin и manager).

### OpenCart XLSX

- **Класс:** `OpenCartXlsxCatalogImport`
- **Назначение:** импорт категорий, товаров и атрибутов из одного XLSX-файла выгрузки OpenCart (например `products-2026-03-03-start-52-end-598.xlsx`).
- **Листы:** используются **Products** (товары и категории) и **ProductAttributes** (product_id, attribute_group_id, attribute_id, text(ru-ru)). Остальные листы (AdditionalImages, ProductOptions, ProductOptionValues) пока не обрабатываются.
- **Конфиг:** `config/catalog_import.php` → секция `config.opencart_xlsx`. Переменная окружения `OPENCART_XLSX_FILE` — путь к XLSX (для запуска из админки).
- **Запуск:**
  - Из консоли: `php artisan catalog:import-opencart-xlsx /путь/к/products-2026-03-03-start-52-end-598.xlsx`
  - Из админки: задать `OPENCART_XLSX_FILE` в .env и нажать «Поставить в очередь» у провайдера «OpenCart XLSX».
- **Зависимость:** `phpoffice/phpspreadsheet` (указана в composer.json; выполнить `composer install`).

## Управление классами импорта (как в Битрикс)

Список доступных классов импорта задаётся в **`config/catalog_import.php`** (массив `importers`). На странице «Синхронизация каталога» в админке отображаются все зарегистрированные классы; для каждого доступна кнопка «Запустить импорт». Добавление нового класса в конфиг автоматически добавляет его в список на странице.

## Добавление новой интеграции

1. Создать класс в этой папке, наследующий `AbstractCatalogImport`.
2. Реализовать методы: `fetchCategoriesRaw()`, `fetchProductsRaw()`, `mapCategories()`, `mapProducts()`, `persistCategories()`, `persistProducts()` (при необходимости переиспользовать логику базового класса или родителя).
3. Реализовать `static getLabel(): string` для отображения в админке.
4. Если нужны настройки (URL, ключи и т.д.): реализовать `static getConfigKey(): ?string` (вернуть ключ, например `my_export`) и добавить блок в `config/catalog_import.php` в секцию `config` под этим ключом. Все настройки импорта/экспорта каталога хранятся только в `config/catalog_import.php`.
5. Зарегистрировать класс в `config/catalog_import.php` в массиве `importers` — после этого он появится на странице «Синхронизация каталога».
