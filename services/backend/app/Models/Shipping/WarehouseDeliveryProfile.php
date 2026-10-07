<?php

namespace App\Models\Shipping;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WarehouseDeliveryProfile extends Model
{
    protected $fillable = [
        'warehouse_id', 'name', 'coverage_type', 'radius_km', 'base_price', 'price_per_km',
        'free_delivery_threshold', 'delivery_days_min', 'delivery_days_max', 'priority',
        'is_active', 'constraints',
    ];

    protected function casts(): array
    {
        return [
            'radius_km' => 'decimal:2',
            'base_price' => 'decimal:2',
            'price_per_km' => 'decimal:4',
            'free_delivery_threshold' => 'decimal:2',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'constraints' => 'array',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'warehouse_delivery_profile_locations'
        )->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
