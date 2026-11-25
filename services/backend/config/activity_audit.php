<?php

return [
    // Explicit opt-in after migration; tests enable independently.
    'enabled' => (bool) env('ACTIVITY_AUDIT_ENABLED', false),
    'behavior' => (bool) env('ACTIVITY_AUDIT_BEHAVIOR', false),
    'retention_days' => ['security' => 365, 'admin' => 365, 'order' => 365, 'account' => 90, 'shopping' => 90, 'behavior' => 30],
];
