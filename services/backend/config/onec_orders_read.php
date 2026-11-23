<?php

return [
    // Separate from the existing outgoing API token and customer sessions.
    // Disabled until production credentials and HTTPS are configured.
    'enabled' => (bool) env('ONEC_ORDERS_READ_ENABLED', false),
    'token' => env('ONEC_ORDERS_READ_TOKEN', ''),
    'per_minute' => (int) env('ONEC_ORDERS_READ_PER_MINUTE', 30),
    'max_per_page' => 100,
];
