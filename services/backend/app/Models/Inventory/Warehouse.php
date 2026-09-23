<?php

namespace App\Models\Inventory;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryMethod;
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
     * Старая прямая связь склада с локациями.
     * Нужна для совместимости с существующими данными,
     * но новые настройки доставки создаются через deliveryMethods().
     */
    public function shippingLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'warehouse_shipping_location',
            'warehouse_id',
            'shipping_location_id'
        )->withTimestamps();
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
