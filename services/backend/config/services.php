<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 1C Integration
    |--------------------------------------------------------------------------
    |
    | Настройки для интеграции с системой 1С
    |
    */

    'integration_1c' => [
        'base_url' => env('ONEC_API_BASE_URL', ''),
        'api_key' => env('ONEC_API_KEY', ''),
        'timeout' => env('ONEC_API_TIMEOUT', 30),
        'enabled' => env('ONEC_API_ENABLED', false),
        'orders_path' => env('ONEC_ORDERS_PATH', '/api/v1/integration/1c/orders'),
        'orders_queue' => env('ONEC_ORDERS_QUEUE', 'integration-1c'),
    ],


    /*
    | SSR-кэш фронта (опционально): POST сброс ключей главной после правки подборок.
    | Пример: FRONTEND_SSR_CACHE_INVALIDATE_URL=http://127.0.0.1:3000/internal/cache/invalidate
    */
    'frontend' => [
        'ssr_cache_invalidate_url' => env('FRONTEND_SSR_CACHE_INVALIDATE_URL'),
        'ssr_cache_invalidate_secret' => env('FRONTEND_SSR_CACHE_INVALIDATE_SECRET'),
    ],
];
