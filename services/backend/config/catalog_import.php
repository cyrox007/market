<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Классы импорта/экспорта каталога
    |--------------------------------------------------------------------------
    |
    | Список классов импорта (реализуют CatalogImportInterface).
    | Отображаются на странице «Синхронизация каталога» в админке.
    | Порядок записей определяет порядок отображения в списке.
    |
    */
    'importers' => [
        \App\Services\Catalog\Integrations\Svetofor1CCatalogImport::class,
        \App\Services\Catalog\Integrations\OpenCartXlsxCatalogImport::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Настройки по классам интеграций
    |--------------------------------------------------------------------------
    |
    | Ключ — значение getConfigKey() класса (например svetofor_1c).
    | Все настройки импорта/экспорта каталога хранятся здесь, а не в services.php.
    |
    */
    'queue' => env('CATALOG_IMPORT_QUEUE', 'default'),

    'config' => [
        'svetofor_1c' => [
            'base_url' => env('SVETOFOR_CATALOG_API_BASE_URL', 'http://api.svetofor-mebel.ru'),
            'api_key' => env('SVETOFOR_CATALOG_API_KEY', ''),
            'timeout' => (int) env('SVETOFOR_CATALOG_API_TIMEOUT', 60),
            // Путь для загрузки остатков (bulk). Пусто = не вызывать. Пример: /api/v1/integration/1c/stock-items
            'stock_path' => env('SVETOFOR_1C_STOCK_PATH', ''),
            'products_cache_path' => env('SVETOFOR_1C_PRODUCTS_CACHE_PATH', '/api/v1/integration/1c/v2/cache/products'),
            'updated_after_fallback_minutes' => (int) env('SVETOFOR_1C_UPDATED_AFTER_FALLBACK_MINUTES', 30),
            'initial_updated_after' => env('SVETOFOR_1C_INITIAL_UPDATED_AFTER', '2000-01-01T00:00:00.000Z'),
            'minute_sync_cursor_key' => env('SVETOFOR_1C_MINUTE_SYNC_CURSOR_KEY', 'svetofor_1c:minute_sync:cursor'),
            'minute_sync_default_lookback_minutes' => (int) env('SVETOFOR_1C_MINUTE_SYNC_DEFAULT_LOOKBACK_MINUTES', 5),
            'minute_sync_use_initial_cursor' => (bool) env('SVETOFOR_1C_MINUTE_SYNC_USE_INITIAL_CURSOR', false),
            'minute_sync_queue' => env('SVETOFOR_1C_MINUTE_SYNC_QUEUE', 'integration-1c'),
            'minute_sync_job_tries' => (int) env('SVETOFOR_1C_MINUTE_SYNC_JOB_TRIES', 3),
            'minute_sync_job_backoff' => (int) env('SVETOFOR_1C_MINUTE_SYNC_JOB_BACKOFF', 20),
            'minute_sync_job_timeout' => (int) env('SVETOFOR_1C_MINUTE_SYNC_JOB_TIMEOUT', 120),
            'force_full_sync_when_catalog_empty' => (bool) env('SVETOFOR_1C_FORCE_FULL_SYNC_WHEN_CATALOG_EMPTY', true),
            'stock_sync_path' => env('SVETOFOR_1C_STOCK_SYNC_PATH', '/api/v1/integration/1c/v2/cache/stocks'),
            'stock_sync_timeout' => (int) env('SVETOFOR_1C_STOCK_SYNC_TIMEOUT', 8),
        ],
        'opencart_xlsx' => [
            'file' => env('OPENCART_XLSX_FILE', ''),
        ],
    ],
];
