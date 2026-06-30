<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanilo\Shipment\Models\ShippingMethod;

class Region extends Model
{
    protected $fillable = [
        'federal_district_id',
        'name',
        'slug',
        'code',
        'type',
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
     * Получить федеральный округ
     */
    public function federalDistrict(): BelongsTo
    {
        return $this->belongsTo(FederalDistrict::class);
    }

    /**
     * Получить населенные пункты региона
     */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class)->orderBy('sort_order');
    }

    /**
     * Получить активные населенные пункты
     */
    public function activeLocalities(): HasMany
    {
        return $this->localities()->where('is_active', true);
    }

    /**
     * Получить shipping method
     */
    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Получить стоимость доставки с учетом наследования от федерального округа
     */
    public function getEffectiveDeliveryPrice(): ?float
    {
        if ($this->delivery_price !== null) {
            return (float) $this->delivery_price;
        }

        return $this->federalDistrict?->getEffectiveDeliveryPrice();
    }

    /**
     * Получить порог бесплатной доставки с учетом наследования
     */
    public function getEffectiveFreeDeliveryThreshold(): ?float
    {
        if ($this->free_delivery_threshold !== null) {
            return (float) $this->free_delivery_threshold;
        }

        return $this->federalDistrict?->getEffectiveFreeDeliveryThreshold();
    }

    /**
     * Получить сроки доставки с учетом наследования
     */
    public function getEffectiveDeliveryDays(): ?array
    {
        if ($this->delivery_days_min !== null || $this->delivery_days_max !== null) {
            return [
                'min' => $this->delivery_days_min,
                'max' => $this->delivery_days_max,
            ];
        }

        return $this->federalDistrict?->getEffectiveDeliveryDays();
    }

    /**
     * Получить shipping method с учетом наследования
     */
    public function getEffectiveShippingMethod(): ?ShippingMethod
    {
        if ($this->shipping_method_id) {
            return $this->shippingMethod;
        }

        return $this->federalDistrict?->shippingMethod;
    }

    /**
     * Получить стоимость сборки с учетом наследования
     */
    public function getEffectiveAssemblyPrice(): ?float
    {
        if ($this->assembly_price !== null) {
            return (float) $this->assembly_price;
        }

        return $this->federalDistrict?->getEffectiveAssemblyPrice();
    }

    /**
     * Получить срок сборки с учетом наследования
     */
    public function getEffectiveAssemblyDays(): ?int
    {
        if ($this->assembly_days !== null) {
            return $this->assembly_days;
        }

        return $this->federalDistrict?->getEffectiveAssemblyDays();
    }

    /**
     * Требуется ли сборка с учетом наследования
     */
    public function getEffectiveRequiresAssembly(): bool
    {
        if ($this->requires_assembly !== null) {
            return $this->requires_assembly;
        }

        return $this->federalDistrict?->getEffectiveRequiresAssembly() ?? false;
    }
}

