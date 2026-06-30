<?php

namespace App\Services\Gateway;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Models\Gateway\GatewayLog;
use Illuminate\Database\Eloquent\Model;

class DatabaseGatewayLogger implements GatewayLoggerInterface
{
    public function log(
        string $gateway,
        string $action,
        string $message,
        array $meta = [],
        ?Model $loggable = null,
        string $channel = 'payment',
        string $level = 'info'
    ): void {
        $orderId = $meta['order_id'] ?? null;
        GatewayLog::create([
            'gateway' => $gateway,
            'channel' => $channel,
            'action' => $action,
            'order_id' => $orderId,
            'message' => $message,
            'level' => $level,
            'meta' => $meta ?: null,
            'loggable_type' => $loggable ? $loggable->getMorphClass() : null,
            'loggable_id' => $loggable?->getKey(),
        ]);
    }
}
