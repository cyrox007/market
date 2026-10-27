<?php

return [
    'enabled' => (bool) env('OPERATOR_DIAGNOSTICS_ENABLED', false),
    'until' => env('OPERATOR_DIAGNOSTICS_UNTIL'),
];
