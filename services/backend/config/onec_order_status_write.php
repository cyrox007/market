<?php

return [
    // Inbound write is opt-in. Only a token scoped orders:status:write is accepted.
    'enabled' => (bool) env('ONEC_ORDER_STATUS_WRITE_ENABLED', false),
    'per_minute' => (int) env('ONEC_ORDER_STATUS_WRITE_PER_MINUTE', 20),
];
