<?php

namespace App\Models\Shipping;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanilo\Shipment\Models\ShippingMethod;

class WarehouseDeliveryMethod extends Model
{
    protected $fillable = [
        'warehouse_id',
        'shipping_method_id',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseDeliveryZone::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
