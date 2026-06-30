<?php

namespace App\Services\Shipping\Contracts;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Collection;

/**
 * Интерфейс для работы с типами обработки доставки
 */
interface DeliveryHandlingProviderInterface
{
    /**
     * Получить доступные типы обработки доставки для локации с учетом иерархии
     *
     * @param ShippingLocation $location Локация доставки
     * @return Collection Коллекция типов обработки доставки
     */
    public function getAvailableHandlingTypes(ShippingLocation $location): Collection;

    /**
     * Получить стоимость обработки доставки для типа обработки и локации
     *
     * @param ShippingLocation $location Локация доставки
     * @param DeliveryHandlingType $handlingType Тип обработки доставки
     * @param int|null $floor Этаж (для ручного подъема)
     * @return float|null Стоимость обработки или null, если не найдена
     */
    public function getHandlingPrice(ShippingLocation $location, DeliveryHandlingType $handlingType, ?int $floor = null): ?float;

    /**
     * Проверить, доступен ли тип обработки для локации (с учетом иерархии)
     *
     * @param ShippingLocation $location Локация доставки
     * @param DeliveryHandlingType $handlingType Тип обработки доставки
     * @return bool
     */
    public function isHandlingTypeAvailable(ShippingLocation $location, DeliveryHandlingType $handlingType): bool;
}
