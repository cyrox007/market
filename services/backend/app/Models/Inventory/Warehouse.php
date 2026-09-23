<?php

namespace App\Models\Inventory;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryMethod;
use App\Models\Shipping\WarehouseDeliveryRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'meta' => 'array',
    ];

    /**
     * Устаревшие прямые связи склада с локациями.
     * Оставлены для совместимости с ранее заведёнными данными.
     */
    public function shippingLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'warehouse_shipping_location',
            'warehouse_id',
            'shipping_location_id'
        )->withPivot([
            'delivery_price',
            'delivery_days_min',
            'delivery_days_max',
            'is_active',
            'priority',
        ])->withTimestamps();
    }

    /**
     * Устаревшие правила склада. Новая логистика использует deliveryMethods().
     */
    public function deliveryRules(): HasMany
    {
        return $this->hasMany(WarehouseDeliveryRule::class);
    }

    public function deliveryMethods(): HasMany
    {
        return $this->hasMany(WarehouseDeliveryMethod::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }
}
