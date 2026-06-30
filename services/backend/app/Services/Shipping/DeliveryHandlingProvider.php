<?php

namespace App\Services\Shipping;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\Contracts\DeliveryHandlingProviderInterface;
use Illuminate\Support\Collection;

/**
 * Сервис для работы с типами обработки доставки
 * Реализует DeliveryHandlingProviderInterface
 */
class DeliveryHandlingProvider implements DeliveryHandlingProviderInterface
{
    /**
     * Получить доступные типы обработки доставки для локации с учетом иерархии
     */
    public function getAvailableHandlingTypes(ShippingLocation $location): Collection
    {
        return $location->getAvailableDeliveryHandlingTypes();
    }

    /**
     * Получить стоимость обработки доставки для типа обработки и локации
     */
    public function getHandlingPrice(ShippingLocation $location, DeliveryHandlingType $handlingType, ?int $floor = null): ?float
    {
        return $location->getDeliveryHandlingPrice($handlingType, $floor);
    }

    /**
     * Проверить, доступен ли тип обработки для локации (с учетом иерархии)
     */
    public function isHandlingTypeAvailable(ShippingLocation $location, DeliveryHandlingType $handlingType): bool
    {
        $availableTypes = $this->getAvailableHandlingTypes($location);
        
        return $availableTypes->contains('id', $handlingType->id);
    }
}
