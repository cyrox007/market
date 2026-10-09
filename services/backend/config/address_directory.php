<?php

return [
    // Independent abuse guards, configurable without changing routes.
    'read_requests_per_minute' => (int) env('ADDRESS_DIRECTORY_READ_RATE', 120),
    'geo_requests_per_minute' => (int) env('ADDRESS_DIRECTORY_GEO_RATE', 30),
    'base_url' => env('ADDRESS_DIRECTORY_BASE_URL', 'https://api.svetofor-mebel.ru'),
    'search_path' => env('ADDRESS_DIRECTORY_SEARCH_PATH', '/api/v2/address/search'),
    'hierarchy_path' => env('ADDRESS_DIRECTORY_HIERARCHY_PATH', '/api/v2/address/hierarchy/{externalId}'),
    'directory_path' => env('ADDRESS_DIRECTORY_PATH', '/api/v2/address/directory'),
    'timeout' => (float) env('ADDRESS_DIRECTORY_TIMEOUT', 3),
    'connect_timeout' => (float) env('ADDRESS_DIRECTORY_CONNECT_TIMEOUT', 1.5),
    'cache_ttl' => (int) env('ADDRESS_DIRECTORY_CACHE_TTL', 86400),
];
