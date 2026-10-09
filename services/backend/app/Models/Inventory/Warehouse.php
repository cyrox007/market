<?php

namespace App\Models\Inventory;

use App\Models\Product\Manufacturer;
use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\WarehouseDeliveryProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'source_type',
        'manufacturer_id',
        'stock_mode',
        'address',
        'address_external_id',
        'gar_guid',
        'kladr_code',
        'address_snapshot',
        'coordinate_source',
        'coordinate_precision',
        'latitude',
        'longitude',
        'processing_days_min',
        'processing_days_max',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'processing_days_min' => 'integer',
        'processing_days_max' => 'integer',
        'meta' => 'array',
        'address_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $warehouse): void {
            $warehouse->external_id ??= (string) Str::uuid();
        });
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function shippingLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingLocation::class,
            'warehouse_shipping_location',
            'warehouse_id',
            'shipping_location_id'
        )->withTimestamps();
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function deliveryProfiles(): HasMany
    {
        return $this->hasMany(WarehouseDeliveryProfile::class);
    }

    public function productAvailabilities(): HasMany
    {
        return $this->hasMany(SourceProductAvailability::class);
    }
}
