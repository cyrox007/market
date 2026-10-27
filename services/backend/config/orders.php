<?php

return [
    'unpaid_auto_cancel_minutes' => (int) env('ORDERS_UNPAID_AUTO_CANCEL_MINUTES', 10),
    'automatic_cancel_refunds' => (bool) env('ORDERS_AUTOMATIC_CANCEL_REFUNDS', false),
];
