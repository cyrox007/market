<?php

namespace App\Models\Shipping;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WarehouseDeliveryProfile extends Model
{
    use \App\Models\Traits\InvalidatesFulfillment;
    protected $fillable = [
        'warehouse_id', 'name', 'coverage_type', 'radius_km', 'base_price', 'price_per_km',
        'free_delivery_threshold', 'delivery_days_min', 'delivery_days_max', 'priority',
        'is_active', 'constraints', 'directory_territories', 'service_tariffs', 'services_location_id',
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
            'directory_territories' => 'array',
            'service_tariffs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->isDirty('service_tariffs') && $profile->service_tariffs !== null) {
                $profile->service_tariffs = app(\App\Services\Shipping\WarehouseServices::class)->validateTariffs($profile->service_tariffs ?? []);
            }
            if ($profile->isDirty('directory_territories') && $profile->directory_territories) {
                $profile->directory_territories = app(\App\Services\Shipping\DirectoryCoverage::class)->verify($profile->directory_territories);
            }
            if ((float) $profile->base_price < 0 || (float) $profile->price_per_km < 0
                || $profile->delivery_days_min < 0 || $profile->delivery_days_max < $profile->delivery_days_min
                || ! in_array($profile->coverage_type ?? 'locations', ['locations', 'radius', 'hybrid'], true)
                || (in_array($profile->coverage_type, ['radius', 'hybrid'], true) && (float) $profile->radius_km <= 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['coverage_type' => 'Проверьте радиус, стоимость и диапазон сроков доставки.']);
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function servicesLocation(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'services_location_id');
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
