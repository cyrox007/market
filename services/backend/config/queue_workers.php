<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Очереди для cron-воркера (queue:work через schedule:run)
    |--------------------------------------------------------------------------
    |
    | default          — оплата (callback), импорт каталога, почта, прочее
    | integration-1c   — отправка заказов в 1С (SyncOrderTo1CJob), синк товаров/остатков
    |
    | В schedule: connection передаётся позиционным аргументом queue:work (не --connection).
    | После изменения: php artisan config:clear
    |
    */

    'connection' => env('QUEUE_CONNECTION', 'database'),

    'queues' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QUEUE_NAMES', 'default,integration-1c'))
    ))),

    /** Максимальное время работы одного запуска воркера (сек), меньше 60 для cron раз в минуту */
    'max_time' => (int) env('QUEUE_WORKER_MAX_TIME', 55),

    'memory' => (int) env('QUEUE_WORKER_MEMORY', 256),

    'timeout' => (int) env('QUEUE_WORKER_TIMEOUT', 120),

    'tries' => (int) env('QUEUE_WORKER_TRIES', 3),

];
