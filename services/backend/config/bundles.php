<?php

return [
    // Enable only after 1C supports products[].warehouseExternalId.
    'multi_warehouse_enabled' => (bool) env('BUNDLE_MULTI_WAREHOUSE_ENABLED', false),
];
