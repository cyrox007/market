<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanilo\Shipment\Models\ShippingMethod;

class FederalDistrict extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'delivery_price',
        'free_delivery_threshold',
        'delivery_days_min',
        'delivery_days_max',
        'is_active',
        'sort_order',
        'shipping_method_id',
        'requires_assembly',
        'assembly_price',
        'assembly_days',
        'min_order_amount',
        'max_order_weight',
        'max_order_volume',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'decimal:2',
            'free_delivery_threshold' => 'decimal:2',
            'assembly_price' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_order_weight' => 'decimal:2',
            'max_order_volume' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_assembly' => 'boolean',
            'sort_order' => 'integer',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'assembly_days' => 'integer',
        ];
    }

    /**
     * Получить регионы федерального округа
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class)->orderBy('sort_order');
    }

    /**
     * Получить активные регионы
     */
    public function activeRegions(): HasMany
    {
        return $this->regions()->where('is_active', true);
    }

    /**
     * Получить shipping method
     */
    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Получить стоимость доставки с учетом наследования
     */
    public function getEffectiveDeliveryPrice(): ?float
    {
        return $this->delivery_price;
    }

    /**
     * Получить порог бесплатной доставки с учетом наследования
     */
    public function getEffectiveFreeDeliveryThreshold(): ?float
    {
        return $this->free_delivery_threshold;
    }

    /**
     * Получить сроки доставки с учетом наследования
     */
    public function getEffectiveDeliveryDays(): ?array
    {
        if ($this->delivery_days_min === null && $this->delivery_days_max === null) {
            return null;
        }

        return [
            'min' => $this->delivery_days_min,
            'max' => $this->delivery_days_max,
        ];
    }

    /**
     * Получить стоимость сборки
     */
    public function getEffectiveAssemblyPrice(): ?float
    {
        return $this->assembly_price ? (float) $this->assembly_price : null;
    }

    /**
     * Получить срок сборки
     */
    public function getEffectiveAssemblyDays(): ?int
    {
        return $this->assembly_days;
    }

    /**
     * Требуется ли сборка
     */
    public function getEffectiveRequiresAssembly(): bool
    {
        return $this->requires_assembly ?? false;
    }
}
