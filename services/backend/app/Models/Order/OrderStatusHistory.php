<?php

namespace App\Models\Order;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'status',
        'comment',
        'user_id',
    ];

    /**
     * Get the order that owns the status history.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who changed the status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return OrderStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    public function getCommentAttribute(?string $value): ?string
    {
        // Present the old automatic creation note without rewriting historical audit data.
        return $value === 'Заказ создан. Период охлаждения 60 минут.' ? 'Заказ создан.' : $value;
    }
}
