<?php

namespace App\Services\Shipping;

use App\Models\Shipping\DeliveryHandlingType;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Str;

class ShippingCalculationService
{
    /**
     * Рассчитать стоимость доставки для локации
     *
     * @param ShippingLocation $location Локация доставки
     * @param float $orderAmount Сумма заказа
     * @param DeliveryHandlingType|null $handlingType Тип обработки доставки
     * @param int|null $floor Этаж (для ручного подъема)
     * @return array Результат расчета
     */
    public function calculateShipping(
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?DeliveryHandlingType $handlingType = null,
        ?int $floor = null
    ): array {
        // Получаем эффективную стоимость доставки с учетом наследования
        $deliveryPrice = $location->getEffectiveDeliveryPrice() ?? 0.0;

        // Проверяем порог бесплатной доставки
        $freeDeliveryThreshold = $location->getEffectiveFreeDeliveryThreshold();
        if ($freeDeliveryThreshold !== null && $orderAmount >= $freeDeliveryThreshold) {
            $deliveryPrice = 0.0;
        }

        // Рассчитываем стоимость обработки доставки (разгрузка, подъем)
        $handlingPrice = null;
        if ($handlingType) {
            $handlingPrice = $location->getDeliveryHandlingPrice($handlingType, $floor);
        }

        // Получаем стоимость сборки
        $assemblyPrice = null;
        if ($location->getEffectiveRequiresAssembly()) {
            $assemblyPrice = $location->getEffectiveAssemblyPrice();
        }

        // Получаем сроки доставки
        $deliveryDays = $location->getEffectiveDeliveryDays();
        $assemblyDays = $location->getEffectiveAssemblyDays();

        // Итоговая стоимость
        $total = $deliveryPrice + ($handlingPrice ?? 0.0) + ($assemblyPrice ?? 0.0);

        return [
            'delivery_price' => $deliveryPrice,
            'handling_price' => $handlingPrice,
            'assembly_price' => $assemblyPrice,
            'total' => $total,
            'free_delivery_threshold' => $freeDeliveryThreshold,
            'delivery_days' => $deliveryDays,
            'assembly_days' => $assemblyDays,
            'requires_assembly' => $location->getEffectiveRequiresAssembly(),
            'shipping_method' => $location->getEffectiveShippingMethod(),
        ];
    }

    /**
     * Найти локацию по названию или slug
     *
     * @param string $query Название или slug
     * @param string|null $type Тип локации (federal_district, region, locality)
     * @return ShippingLocation|null
     */
    public function findLocation(string $query, ?string $type = null): ?ShippingLocation
    {
        $query = Str::slug($query);

        $location = ShippingLocation::where('slug', $query)
            ->orWhere('name', 'like', "%{$query}%");

        if ($type) {
            $location->where('type', $type);
        }

        return $location->where('is_active', true)->first();
    }

    /**
     * Получить все доступные типы обработки доставки
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableDeliveryHandlingTypes()
    {
        return DeliveryHandlingType::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Проверить возможность доставки в локацию
     *
     * @param ShippingLocation $location
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
    ): array {
        $reasons = [];

        // Проверка минимальной суммы заказа (с учетом наследования)
        $minOrderAmount = $location->min_order_amount ?? $location->parent?->min_order_amount;
        if ($minOrderAmount !== null && $orderAmount < $minOrderAmount) {
            $reasons[] = "Минимальная сумма заказа: {$minOrderAmount} руб.";
        }

        // Проверка максимального веса (с учетом наследования)
        $maxWeight = $location->max_order_weight ?? $location->parent?->max_order_weight;
        if ($orderWeight !== null && $maxWeight !== null && $orderWeight > $maxWeight) {
            $reasons[] = "Максимальный вес заказа: {$maxWeight} кг";
        }

        // Проверка максимального объема (с учетом наследования)
        $maxVolume = $location->max_order_volume ?? $location->parent?->max_order_volume;
        if ($orderVolume !== null && $maxVolume !== null && $orderVolume > $maxVolume) {
            $reasons[] = "Максимальный объем заказа: {$maxVolume} м³";
        }

        // Проверка активности локации и всех родителей
        $currentLocation = $location;
        while ($currentLocation) {
            if (!$currentLocation->is_active) {
                $reasons[] = "Доставка в '{$currentLocation->name}' временно недоступна";
                break;
            }
            $currentLocation = $currentLocation->parent;
        }

        return [
            'available' => empty($reasons),
            'reasons' => $reasons,
        ];
    }
}
