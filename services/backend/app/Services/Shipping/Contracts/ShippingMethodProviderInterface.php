<?php

namespace App\Services\Shipping\Contracts;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Collection;
use Vanilo\Shipment\Models\ShippingMethod;

/**
 * Интерфейс для получения доступных методов доставки
 */
interface ShippingMethodProviderInterface
{
    /**
     * Получить доступные методы доставки для локации
     *
     * @param ShippingLocation $location Локация доставки
     * @return Collection Коллекция методов доставки
     */
    public function getAvailableShippingMethods(ShippingLocation $location): Collection;

    /**
     * Получить информацию о методе доставки для API
     *
     * @param ShippingMethod $method Метод доставки
     * @param ShippingLocation $location Локация доставки
     * @param float $orderAmount Сумма заказа
     * @return array Данные метода доставки для API
     */
    public function getMethodInfo(ShippingMethod $method, ShippingLocation $location, float $orderAmount = 0.0): array;
}
