<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanilo\Shipment\Models\ShippingMethod;
use App\Models\Shipping\Carrier;
use App\Models\Shipping\AdditionalService;

/**
 * Единая модель для иерархии адресов доставки
 * Использует self-referencing для построения иерархии
 */
class ShippingLocation extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ShippingLocationFactory::new();
    }

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'code',
        'type',
        'location_type',
        'postal_code',
        'pickup_enabled',
        'pickup_notice',
        'delivery_price',
        'free_delivery_threshold',
        'delivery_days_min',
        'delivery_days_max',
        'shipping_method_id',
        'requires_assembly',
        'assembly_price',
        'assembly_days',
        'min_order_amount',
        'max_order_weight',
        'max_order_volume',
        'is_active',
        'sort_order',
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
            'pickup_enabled' => 'boolean',
            'requires_assembly' => 'boolean',
            'sort_order' => 'integer',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'assembly_days' => 'integer',
        ];
    }

    /**
     * Получить родительскую локацию
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'parent_id');
    }

    /**
     * Получить дочерние локации
     */
    public function children(): HasMany
    {
        return $this->hasMany(ShippingLocation::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Получить активные дочерние локации
     */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /**
     * Получить типы обработки доставки для локации
     */
    public function deliveryHandlingTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryHandlingType::class,
            'shipping_location_delivery_handling',
            'shipping_location_id',
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
     * Получить shipping method
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Получить carriers для локации
     */
    public function carriers(): BelongsToMany
    {
        return $this->belongsToMany(
            Carrier::class,
            'carrier_shipping_location',
            'shipping_location_id',
            'carrier_id'
        )->withPivot(['base_price', 'free_delivery_threshold', 'delivery_days_min', 'delivery_days_max', 'is_active', 'sort_order'])
            ->withTimestamps()
            ->orderBy('carrier_shipping_location.sort_order');
    }

    /**
     * Получить активные carriers для локации
     */
    public function activeCarriers(): BelongsToMany
    {
        return $this->carriers()->wherePivot('is_active', true);
    }

    /**
     * Получить методы оплаты для локации
     */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Payment\PaymentMethod::class,
            'payment_method_shipping_location',
            'shipping_location_id',
            'payment_method_id'
        )->withPivot(['is_active', 'sort_order'])
            ->withTimestamps()
            ->orderBy('payment_method_shipping_location.sort_order');
    }

    /**
     * Получить активные методы оплаты для локации
     */
    public function activePaymentMethods(): BelongsToMany
    {
        return $this->paymentMethods()
            ->wherePivot('is_active', true)
            ->where('payment_methods.is_active', true)
            ->where('payment_methods.is_enabled', true);
    }

    /**
     * Получить стоимость доставки с учетом наследования по иерархии
     */
    public function getEffectiveDeliveryPrice(): ?float
    {
        if ($this->delivery_price !== null) {
            return (float) $this->delivery_price;
        }

        return $this->parent?->getEffectiveDeliveryPrice();
    }

    /**
     * Получить порог бесплатной доставки с учетом наследования
     */
    public function getEffectiveFreeDeliveryThreshold(): ?float
    {
        if ($this->free_delivery_threshold !== null) {
            return (float) $this->free_delivery_threshold;
        }

        return $this->parent?->getEffectiveFreeDeliveryThreshold();
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

        return $this->parent?->getEffectiveDeliveryDays();
    }

    /**
     * Определить, доступен ли самовывоз для локации с учетом наследования.
     * По умолчанию включен для обратной совместимости.
     */
    public function getEffectivePickupEnabled(): bool
    {
        if ($this->pickup_enabled !== null) {
            return (bool) $this->pickup_enabled;
        }

        if ($this->parent) {
            return $this->parent->getEffectivePickupEnabled();
        }

        return true;
    }

    /**
     * Получить shipping method с учетом наследования
     */
    public function getEffectiveShippingMethod(): ?ShippingMethod
    {
        if ($this->shipping_method_id) {
            return $this->shippingMethod;
        }

        return $this->parent?->getEffectiveShippingMethod();
    }

    /**
     * Получить carriers с учетом наследования
     * Если у текущей локации нет carriers, берем от родителя
     */
    public function getEffectiveCarriers()
    {
        $carriers = $this->activeCarriers()->get();

        if ($carriers->isEmpty() && $this->parent) {
            return $this->parent->getEffectiveCarriers();
        }

        return $carriers;
    }

    /**
     * Получить методы оплаты с учетом наследования
     * Если у текущей локации нет методов оплаты, берем от родителя
     */
    public function getEffectivePaymentMethods()
    {
        $paymentMethods = $this->activePaymentMethods()->get();

        if ($paymentMethods->isEmpty() && $this->parent) {
            return $this->parent->getEffectivePaymentMethods();
        }

        return $paymentMethods;
    }

    /**
     * Получить цену доставки для конкретного carrier с учетом наследования
     */
    public function getCarrierPrice(Carrier $carrier, float $orderAmount = 0.0): ?float
    {
        // Сначала проверяем pivot для текущей локации
        $pivot = $this->carriers()
            ->where('carriers.id', $carrier->id)
            ->wherePivot('is_active', true)
            ->first()?->pivot;

        if ($pivot && $pivot->base_price !== null) {
            $price = (float) $pivot->base_price;

            // Проверяем порог бесплатной доставки из pivot
            $threshold = $pivot->free_delivery_threshold;
            if ($threshold !== null && $orderAmount >= $threshold) {
                return 0.0;
            }

            return $price;
        }

        // Если нет в текущей локации, проверяем родителя
        if ($this->parent) {
            return $this->parent->getCarrierPrice($carrier, $orderAmount);
        }

        // Если нет нигде, возвращаем базовую цену доставки локации
        return $this->getEffectiveDeliveryPrice();
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
            // Если у текущей локации нет цены, проверяем родителя
            return $this->parent?->getDeliveryHandlingPrice($handlingType, $floor);
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

        // Если у текущей локации нет цены, проверяем родителя
        return $this->parent?->getDeliveryHandlingPrice($handlingType, $floor);
    }

    /**
     * Получить свойства сборки с учетом наследования
     */
    public function getEffectiveAssemblyPrice(): ?float
    {
        if ($this->assembly_price !== null) {
            return (float) $this->assembly_price;
        }

        return $this->parent?->getEffectiveAssemblyPrice();
    }

    /**
     * Получить срок сборки с учетом наследования
     */
    public function getEffectiveAssemblyDays(): ?int
    {
        if ($this->assembly_days !== null) {
            return $this->assembly_days;
        }

        return $this->parent?->getEffectiveAssemblyDays();
    }

    /**
     * Требуется ли сборка с учетом наследования
     */
    public function getEffectiveRequiresAssembly(): bool
    {
        if ($this->requires_assembly !== null) {
            return $this->requires_assembly;
        }

        return $this->parent?->getEffectiveRequiresAssembly() ?? false;
    }

    /**
     * Получить полный путь локации (например: "Центральный округ > Москва > г. Москва")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Получить все родительские локации (включая саму локацию)
     * Используется для поиска правил с учетом наследования
     * 
     * @return array Массив ID локаций от текущей локации к корню [текущая, родитель, дедушка, ...]
     */
    public function getAncestorsIds(): array
    {
        $ids = [$this->id];
        $parent = $this->parent;

        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }

        return $ids;
    }

    /**
     * Получить все дочерние локации (рекурсивно)
     * Используется для применения правил ко всем дочерним локациям
     * 
     * @return array Массив ID всех дочерних локаций
     */
    public function getDescendantsIds(): array
    {
        $ids = [];
        $children = $this->children()->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantsIds());
        }

        return $ids;
    }

    /**
     * Получить доступные типы обработки доставки с учетом иерархии локаций
     * Ищет типы обработки от текущей локации к корню (от специфичного к общему)
     * 
     * @return \Illuminate\Support\Collection Коллекция типов обработки доставки
     */
    public function getAvailableDeliveryHandlingTypes(): \Illuminate\Support\Collection
    {
        $locationIds = array_reverse($this->getAncestorsIds()); // От специфичного к общему
        
        $handlingTypeIds = [];
        
        // Ищем типы обработки для каждой локации от текущей к корню
        foreach ($locationIds as $locId) {
            $pivots = \DB::table('shipping_location_delivery_handling')
                ->where('shipping_location_id', $locId)
                ->where('is_active', true)
                ->pluck('delivery_handling_type_id')
                ->toArray();
            
            if (!empty($pivots)) {
                $handlingTypeIds = array_merge($handlingTypeIds, $pivots);
            }
        }
        
        // Убираем дубликаты и получаем типы обработки
        $handlingTypeIds = array_unique($handlingTypeIds);
        
        if (empty($handlingTypeIds)) {
            return \Illuminate\Support\Collection::make([]);
        }
        
        return DeliveryHandlingType::whereIn('id', $handlingTypeIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Получить дополнительные услуги для локации
     */
    public function additionalServices(): BelongsToMany
    {
        return $this->belongsToMany(
            AdditionalService::class,
            'shipping_location_additional_service',
            'shipping_location_id',
            'additional_service_id'
        )->withPivot(['price', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Получить активные дополнительные услуги
     */
    public function activeAdditionalServices(): BelongsToMany
    {
        return $this->additionalServices()->wherePivot('is_active', true);
    }

    /**
     * Получить доступные дополнительные услуги с учетом иерархии локаций
     * Ищет услуги от текущей локации к корню (от специфичного к общему)
     * 
     * @return \Illuminate\Support\Collection Коллекция дополнительных услуг
     */
    public function getAvailableAdditionalServices(): \Illuminate\Support\Collection
    {
        $locationIds = array_reverse($this->getAncestorsIds()); // От специфичного к общему
        
        $serviceIds = [];
        
        // Ищем услуги для каждой локации от текущей к корню
        foreach ($locationIds as $locId) {
            $pivots = \DB::table('shipping_location_additional_service')
                ->where('shipping_location_id', $locId)
                ->where('is_active', true)
                ->pluck('additional_service_id')
                ->toArray();
            
            if (!empty($pivots)) {
                $serviceIds = array_merge($serviceIds, $pivots);
            }
        }
        
        // Убираем дубликаты и получаем услуги
        $serviceIds = array_unique($serviceIds);
        
        if (empty($serviceIds)) {
            return \Illuminate\Support\Collection::make([]);
        }
        
        return AdditionalService::whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($service) {
                // Добавляем цену для текущей локации
                $service->effective_price = $service->getPriceForLocation($this);
                return $service;
            });
    }
}

