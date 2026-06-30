<?php

namespace App\Services\Shipping\Contracts;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\DeliveryHandlingType;
use Vanilo\Shipment\Models\ShippingMethod;

/**
 * Интерфейс для расчета стоимости доставки
 */
interface ShippingCostCalculatorInterface
{
    /**
     * Рассчитать стоимость доставки для локации
     *
     * @param ShippingLocation $location Локация доставки
     * @param float $orderAmount Сумма заказа
     * @param DeliveryHandlingType|null $handlingType Тип обработки доставки
     * @param int|null $floor Этаж (для ручного подъема)
     * @param bool $requiresAssembly Требуется ли сборка
     * @return \App\Services\Shipping\DTO\ShippingCalculationResult Результат расчета
     */
    public function calculateForLocation(
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?DeliveryHandlingType $handlingType = null,
        ?int $floor = null,
        bool $requiresAssembly = false
    ): \App\Services\Shipping\DTO\ShippingCalculationResult;

    /**
     * Рассчитать стоимость доставки для конкретного метода доставки
     *
     * @param ShippingMethod $shippingMethod Метод доставки
     * @param ShippingLocation $location Локация доставки
     * @param float $orderAmount Сумма заказа
     * @param DeliveryHandlingType|null $handlingType Тип обработки доставки
     * @param int|null $floor Этаж (для ручного подъема)
     * @param bool $requiresAssembly Требуется ли сборка
     * @return \App\Services\Shipping\DTO\ShippingCalculationResult Результат расчета
     */
    public function calculateForMethod(
        ShippingMethod $shippingMethod,
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?DeliveryHandlingType $handlingType = null,
        ?int $floor = null,
        bool $requiresAssembly = false
    ): \App\Services\Shipping\DTO\ShippingCalculationResult;

    /**
     * Проверить возможность доставки в локацию
     *
     * @param ShippingLocation $location Локация доставки
     * @param float $orderAmount Сумма заказа
     * @param float|null $orderWeight Вес заказа в кг
     * @param float|null $orderVolume Объем заказа в м³
     * @return array ['available' => bool, 'reasons' => array]
     */
    public function checkDeliveryAvailability(
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?float $orderWeight = null,
        ?float $orderVolume = null
    ): array;
}
