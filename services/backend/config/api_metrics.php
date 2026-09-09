<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API timing metrics
    |--------------------------------------------------------------------------
    */

    'enabled' => env('API_METRICS_ENABLED', true),

    'header_response_time' => env('API_METRICS_HEADER_RESPONSE_TIME', true),

    'log_slow_threshold_ms' => (int) env('API_METRICS_LOG_SLOW_THRESHOLD_MS', 1000),

    'store_enabled' => env('API_METRICS_STORE_ENABLED', true),

    'redis_prefix' => env('API_METRICS_REDIS_PREFIX', 'api_metrics:'),

    'ttl_seconds' => (int) env('API_METRICS_TTL_SECONDS', 3600),

    'window_max_requests' => (int) env('API_METRICS_WINDOW_MAX_REQUESTS', 1000),

    'metrics_route' => env('API_METRICS_ROUTE', 'api-docs/metrics'),

    'metrics_route_enabled' => env('API_METRICS_ROUTE_ENABLED', true),

    // Р-5: доступ к публичному роуту метрик только по токену. Пусто = роут закрыт (fail-closed).
    'token' => env('API_METRICS_TOKEN', ''),

];
