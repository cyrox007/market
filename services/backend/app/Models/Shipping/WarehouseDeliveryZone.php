<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDeliveryZone extends Model
{
    protected $table = 'warehouse_delivery_method_locations';

    protected $fillable = [
        'warehouse_delivery_method_id',
        'shipping_location_id',
        'delivery_price',
        'free_delivery_threshold',
        'delivery_days_min',
        'delivery_days_max',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'decimal:2',
            'free_delivery_threshold' => 'decimal:2',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(
            WarehouseDeliveryMethod::class,
            'warehouse_delivery_method_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
