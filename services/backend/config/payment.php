<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Raiffeisen Acquiring (платёжная форма / СБП)
    |--------------------------------------------------------------------------
    | В .env обязательно задать:
    |   RAIFFEISEN_PUBLIC_ID=MB0000284014  (идентификатор продавца из ЛК банка)
    | После изменения .env выполнить: php artisan config:clear
    */
    'raiffeisen' => [
        'public_id' => env('RAIFFEISEN_PUBLIC_ID', ''),
        'secret_key' => env('RAIFFEISEN_SECRET_KEY', ''),
        'url' => env('RAIFFEISEN_PAYMENT_URL', 'https://pay-test.raif.ru/pay'),
        'callback_url' => env('RAIFFEISEN_CALLBACK_URL', null),
    ],

    'raiffeisen_callback_path' => 'api/v1/payment/raiffeisen/callback',

    /*
    |--------------------------------------------------------------------------
    | Raiffeisen e-commerce API (pay.raif.ru) — оплата картой для всех регионов
    |--------------------------------------------------------------------------
    | Test: https://pay-test.raif.ru
    | Prod: https://pay.raif.ru
    | В .env: RAIFFEISEN_ECOM_PUBLIC_ID, RAIFFEISEN_ECOM_SECRET_KEY
    |
    | ВАЖНО: WAF банка может блокировать (445 "Malicious activity blocked"):
    | - localhost в successUrl/failUrl — используйте ngrok или реальный домен для тестов
    | - запросы с IP дата-центров — обращайтесь в ecom@raiffeisen.ru для разблокировки
    | Callback URL настраивается в ЛК Raif Pay и должен указывать на backend: APP_URL + /api/v1/payment/raiffeisen-ecom/callback
    */
    'raiffeisen_ecom' => [
        'public_id' => env('RAIFFEISEN_ECOM_PUBLIC_ID', ''),
        'secret_key' => env('RAIFFEISEN_ECOM_SECRET_KEY', ''),
        'is_test' => env('RAIFFEISEN_ECOM_IS_TEST', true),
        'callback_url' => env('RAIFFEISEN_ECOM_CALLBACK_URL', null),
    ],

    'raiffeisen_ecom_callback_path' => 'api/v1/payment/raiffeisen-ecom/callback',

    /*
    |--------------------------------------------------------------------------
    | Sberbank e-commerce acquiring (register.do)
    |--------------------------------------------------------------------------
    | Тест: https://ecomtest.sberbank.ru — POST /ecomm/gw/partner/api/v1/register.do (JSON)
    | Документация: https://ecomtest.sberbank.ru/doc
    |
    | В .env:
    |   SBERBANK_ACQUIRING_USERNAME=testMerchant_071
    |   SBERBANK_ACQUIRING_PASSWORD=...  (в кавычках, если есть % и спецсимволы)
    |   SBERBANK_ACQUIRING_BASE_URL=https://ecomtest.sberbank.ru
    |   SBERBANK_ACQUIRING_API_FORMAT=ecom
    |
    | Пром: base_url и креды из ЛК (userName / merchantLogin), api_format=ecom
    */
    'sberbank_acquiring' => [
        'username' => env('SBERBANK_ACQUIRING_USERNAME', ''),
        'password' => env('SBERBANK_ACQUIRING_PASSWORD', ''),
        'base_url' => env('SBERBANK_ACQUIRING_BASE_URL', 'https://ecomtest.sberbank.ru'),
        'api_format' => env('SBERBANK_ACQUIRING_API_FORMAT', 'ecom'), // ecom | legacy (3dsec /payment/rest/)
        'register_path' => env('SBERBANK_ACQUIRING_REGISTER_PATH', '/ecomm/gw/partner/api/v1/register.do'),
        'pay_page_path' => env('SBERBANK_ACQUIRING_PAY_PAGE_PATH', '/pp/pay_ru'),
        'callback_url' => env('SBERBANK_ACQUIRING_CALLBACK_URL', null),
        'verify_ssl' => (bool) env('SBERBANK_ACQUIRING_VERIFY_SSL', true),
    ],

    'sberbank_acquiring_callback_path' => 'api/v1/payment/sberbank/callback',
];
