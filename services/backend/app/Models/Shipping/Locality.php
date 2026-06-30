<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Vanilo\Shipment\Models\ShippingMethod;

class Locality extends Model
{
    protected $fillable = [
        'region_id',
        'name',
        'slug',
        'type',
        'postal_code',
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
     * Получить регион
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Получить shipping method
     */
    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Получить типы обработки доставки для населенного пункта
     */
    public function deliveryHandlingTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryHandlingType::class,
            'locality_delivery_handling',
            'locality_id',
            'delivery_handling_type_id'
        )->withPivot(['base_price', 'elevator_price', 'floor_prices', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Получить активные типы обработки доставки
     */
    public function activeDeliveryHandlingTypes(): BelongsToMany
    {
        return $this->deliveryHandlingTypes()->wherePivot('is_active', true);
    }

    /**
     * Получить стоимость доставки с учетом наследования по иерархии
     */
    public function getEffectiveDeliveryPrice(): ?float
    {
        // Приоритет: город -> регион -> федеральный округ
        if ($this->delivery_price !== null) {
            return (float) $this->delivery_price;
        }

        return $this->region?->getEffectiveDeliveryPrice();
    }

    /**
     * Получить порог бесплатной доставки с учетом наследования
     */
    public function getEffectiveFreeDeliveryThreshold(): ?float
    {
        if ($this->free_delivery_threshold !== null) {
            return (float) $this->free_delivery_threshold;
        }

        return $this->region?->getEffectiveFreeDeliveryThreshold();
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

        return $this->region?->getEffectiveDeliveryDays();
    }

    /**
     * Получить shipping method с учетом наследования
     */
    public function getEffectiveShippingMethod(): ?ShippingMethod
    {
        if ($this->shipping_method_id) {
            return $this->shippingMethod;
        }

        return $this->region?->getEffectiveShippingMethod();
    }

    /**
     * Получить стоимость обработки доставки для типа обработки
     */
    public function getDeliveryHandlingPrice(DeliveryHandlingType $handlingType, ?int $floor = null): ?float
    {
        $pivot = $this->deliveryHandlingTypes()
            ->where('delivery_handling_type_id', $handlingType->id)
            ->wherePivot('is_active', true)
            ->first()?->pivot;

        if (!$pivot) {
            return null;
        }

        // Если это лифт, возвращаем фиксированную цену
        if ($handlingType->code === 'elevator' && $pivot->elevator_price !== null) {
            return (float) $pivot->elevator_price;
        }

        // Если указан этаж и есть цены по этажам
        if ($floor !== null && $pivot->floor_prices) {
            $floorPrices = is_string($pivot->floor_prices)
                ? json_decode($pivot->floor_prices, true)
                : $pivot->floor_prices;

            if (isset($floorPrices[$floor])) {
                return (float) $floorPrices[$floor];
            }
        }

        // Возвращаем базовую цену обработки
        if ($pivot->base_price !== null) {
            return (float) $pivot->base_price;
        }

        return null;
    }

    /**
     * Получить свойства сборки с учетом наследования
     */
    public function getEffectiveAssemblyPrice(): ?float
    {
        if ($this->assembly_price !== null) {
            return (float) $this->assembly_price;
        }

        return $this->region?->getEffectiveAssemblyPrice();
    }

    /**
     * Получить срок сборки с учетом наследования
     */
    public function getEffectiveAssemblyDays(): ?int
    {
        if ($this->assembly_days !== null) {
            return $this->assembly_days;
        }

        return $this->region?->getEffectiveAssemblyDays();
    }

    /**
     * Требуется ли сборка с учетом наследования
     */
    public function getEffectiveRequiresAssembly(): bool
    {
        if ($this->requires_assembly !== null) {
            return $this->requires_assembly;
        }

        return $this->region?->getEffectiveRequiresAssembly() ?? false;
    }
}

