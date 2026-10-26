<?php

namespace App\Models\Pricing;

use Illuminate\Database\Eloquent\Model;

class PrivatePriceLink extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'expires_at' => 'datetime'];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Product\Product::class);
    }

    public function activeNow(): bool
    {
        return $this->is_active && (! $this->starts_at || now()->gte($this->starts_at)) && now()->lt($this->expires_at);
    }

    public function rotateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->token_hash = hash('sha256', $token);
        $this->save();
        return $token;
    }

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            if ($link->isDirty('product_id')) { $link->unsetRelation('product'); }
            if ((float) $link->price <= 0 || ! $link->expires_at || ($link->starts_at && $link->expires_at <= $link->starts_at)
                || ! $link->product || $link->product->isVariable()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['price' => 'Укажите положительную цену, срок и конкретный товар/SKU.']);
            }
            $link->token_hash ??= hash('sha256', bin2hex(random_bytes(32)));
        });
    }
}
