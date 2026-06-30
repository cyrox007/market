<?php

namespace App\Models\Gateway;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Запись лога взаимодействия со шлюзом (оплата, доставка и т.д.).
 *
 * @property int $id
 * @property string $gateway
 * @property string $channel
 * @property string $action
 * @property int|null $order_id
 * @property string|null $loggable_type
 * @property int|null $loggable_id
 * @property string|null $message
 * @property string $level
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 */
class GatewayLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'gateway',
        'channel',
        'action',
        'order_id',
        'loggable_type',
        'loggable_id',
        'message',
        'level',
        'meta',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getOrderIdAttribute(): ?int
    {
        return $this->meta['order_id'] ?? null;
    }

    public function getOrderNumberAttribute(): ?string
    {
        return $this->meta['order_number'] ?? null;
    }

    public function getAmountAttribute(): ?float
    {
        $a = $this->meta['amount'] ?? null;
        return $a !== null ? (float) $a : null;
    }
}
