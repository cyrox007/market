<?php

return [
    // Enable after the receiving API migration supports per-line warehouse sources.
    'multi_warehouse_enabled' => (bool) env('BUNDLE_MULTI_WAREHOUSE_ENABLED', false),
];
