<?php

namespace App\Services\Shipping\DTO;

/**
 * Результат расчета стоимости доставки
 */
class ShippingCalculationResult
{
    public function __construct(
        public readonly float $deliveryPrice,
        public readonly ?float $handlingPrice = null,
        public readonly ?float $assemblyPrice = null,
        public readonly ?float $freeDeliveryThreshold = null,
        public readonly ?array $deliveryDays = null,
        public readonly ?int $assemblyDays = null,
        public readonly bool $requiresAssembly = false,
        public readonly ?int $deliveryDaysMin = null,
        public readonly ?int $deliveryDaysMax = null,
        public readonly ?float $basePrice = null,
    ) {
    }

    /**
     * Получить итоговую стоимость доставки (доставка + обработка)
     */
    public function getDeliveryTotal(): float
    {
        return $this->deliveryPrice + ($this->handlingPrice ?? 0.0);
    }

    /**
     * Получить итоговую стоимость всех услуг (доставка + обработка + сборка)
     */
    public function getTotal(): float
    {
        return $this->getDeliveryTotal() + ($this->assemblyPrice ?? 0.0);
    }

    /**
     * Преобразовать в массив для API ответа
     */
    public function toArray(): array
    {
        return [
            'delivery_price' => $this->deliveryPrice,
            'handling_price' => $this->handlingPrice,
            'assembly_price' => $this->assemblyPrice,
            'total' => $this->getTotal(),
            'free_delivery_threshold' => $this->freeDeliveryThreshold,
            'delivery_days' => $this->deliveryDays,
            'delivery_days_min' => $this->deliveryDaysMin,
            'delivery_days_max' => $this->deliveryDaysMax,
            'assembly_days' => $this->assemblyDays,
            'requires_assembly' => $this->requiresAssembly,
            'base_price' => $this->basePrice,
        ];
    }
}
