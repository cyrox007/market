<?php

return [
    'building_points_path' => env('ADDRESS_BUILDING_POINTS_PATH', base_path('../../storage/geodata/osm/building-points.sqlite')),
    'locality_points_path' => env('ADDRESS_LOCALITY_POINTS_PATH', base_path('../../storage/geodata/locality-coordinates.jsonl')),
    'map_tiles' => env('ADDRESS_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    // Independent abuse guards, configurable without changing routes.
    'read_requests_per_minute' => (int) env('ADDRESS_DIRECTORY_READ_RATE', 120),
    'geo_requests_per_minute' => (int) env('ADDRESS_DIRECTORY_GEO_RATE', 30),
    'base_url' => env('ADDRESS_DIRECTORY_BASE_URL', 'https://api.svetofor-mebel.ru'),
    'search_path' => env('ADDRESS_DIRECTORY_SEARCH_PATH', '/api/v2/address/search'),
    'hierarchy_path' => env('ADDRESS_DIRECTORY_HIERARCHY_PATH', '/api/v2/address/hierarchy/{externalId}'),
    'directory_path' => env('ADDRESS_DIRECTORY_PATH', '/api/v2/address'),
    'timeout' => (float) env('ADDRESS_DIRECTORY_TIMEOUT', 3),
    'connect_timeout' => (float) env('ADDRESS_DIRECTORY_CONNECT_TIMEOUT', 1.5),
    'cache_ttl' => (int) env('ADDRESS_DIRECTORY_CACHE_TTL', 86400), // hierarchy: strict, no stale fallback
    // Fresh list TTLs. The backend must keep frequently requested cities and
    // regions locally instead of reaching the upstream directory every time.
    'options_ttl' => [
        'regions' => (int) env('ADDRESS_DIRECTORY_REGIONS_TTL', 86400),
        'localities' => (int) env('ADDRESS_DIRECTORY_LOCALITIES_TTL', 3600),
        'streets' => (int) env('ADDRESS_DIRECTORY_STREETS_TTL', 1800),
        'buildings' => (int) env('ADDRESS_DIRECTORY_BUILDINGS_TTL', 300),
    ],
    'search_ttl' => (int) env('ADDRESS_DIRECTORY_SEARCH_TTL', 300),
    // Keep the last successful, NON-EMPTY listing longer than its fresh TTL.
    // Only used when the upstream API fails, NEVER to validate delivery orders.
    'stale_enabled' => (bool) env('ADDRESS_DIRECTORY_STALE_ENABLED', true),
    'stale_ttl' => (int) env('ADDRESS_DIRECTORY_STALE_TTL', 604800), // 7 days
    'search_stale_ttl' => (int) env('ADDRESS_DIRECTORY_SEARCH_STALE_TTL', 86400), // 1 day
    // Optional hourly warm-up of popular city queries, configured by operator.
    'warm_schedule' => (bool) env('ADDRESS_DIRECTORY_WARM_SCHEDULE', false),
    'warm_localities' => (string) env('ADDRESS_DIRECTORY_WARM_LOCALITIES', ''),

];
