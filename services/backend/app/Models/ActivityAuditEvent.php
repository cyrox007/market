<?php

namespace App\Models;

use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActivityAuditEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'subject_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'subject_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are immutable'));
        static::deleting(fn () => throw new \LogicException('Audit records are immutable'));
    }
}
