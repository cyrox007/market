<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Shipment\Models\ShippingMethod;

/**
 * Связь регионов с методами доставки
 * 
 * Позволяет настраивать доступные методы доставки для каждого региона
 */
class RegionShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_location_id',
        'shipping_method_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Получить регион
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    /**
     * Получить метод доставки
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Scope для получения только активных связей
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для получения связей по региону
     */
    public function scopeForRegion($query, $regionId)
    {
        return $query->where('shipping_location_id', $regionId);
    }

    /**
     * Scope для сортировки по sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
