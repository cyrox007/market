<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Services\Shipping\Contracts\ShippingMethodProviderInterface;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Vanilo\Shipment\Models\ShippingMethod;

/**
 * Сервис для получения доступных методов доставки
 * Реализует ShippingMethodProviderInterface
 */
class ShippingMethodProvider implements ShippingMethodProviderInterface
{
    public function __construct(
        private ProductRegionRuleService $regionRuleService
    ) {
    }

    /**
     * Получить доступные методы доставки для локации
     */
    public function getAvailableShippingMethods(ShippingLocation $location): Collection
    {
        $methods = $this->regionRuleService->getAvailableShippingMethods($location);

        Log::debug('ShippingMethodProvider: methods for location', [
            'location_id' => $location->id,
            'count' => $methods->count(),
            'ids' => $methods->pluck('id')->all(),
        ]);

        return $methods;
    }

    /**
     * Получить информацию о методе доставки для API
     */
    public function getMethodInfo(ShippingMethod $method, ShippingLocation $location, float $orderAmount = 0.0): array
    {
        // Загружаем carrier, если не загружен
        if (!$method->relationLoaded('carrier')) {
            $method->load('carrier');
        }

        // Получаем настройки из конфигурации метода
        $config = $method->configuration ?? [];
        $source = $config['source'] ?? null;
        $carrierModel = $method->carrier;
        if ($source === null && $carrierModel !== null) {
            $source = $carrierModel->name === 'Тариф локации (системный)'
                ? ProductRegionRuleService::SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK
                : ProductRegionRuleService::SHIPPING_METHOD_SOURCE_CARRIER;
        }
        $priority = (int) ($config['sort_order'] ?? 0);

        if ($source === ProductRegionRuleService::SHIPPING_METHOD_SOURCE_CARRIER) {
            $basePrice = isset($config['base_price']) ? (float) $config['base_price'] : 0.0;
            $freeThreshold = array_key_exists('free_delivery_threshold', $config)
                ? $config['free_delivery_threshold']
                : null;
            $deliveryDaysMin = $config['delivery_days_min'] ?? null;
            $deliveryDaysMax = $config['delivery_days_max'] ?? null;
        } else {
            $basePrice = isset($config['base_price'])
                ? (float) $config['base_price']
                : (float) ($location->getEffectiveDeliveryPrice() ?? 0);
            $freeThreshold = array_key_exists('free_delivery_threshold', $config)
                ? $config['free_delivery_threshold']
                : $location->getEffectiveFreeDeliveryThreshold();
            $effectiveDays = $location->getEffectiveDeliveryDays();
            $deliveryDaysMin = $config['delivery_days_min'] ?? ($effectiveDays['min'] ?? $location->delivery_days_min);
            $deliveryDaysMax = $config['delivery_days_max'] ?? ($effectiveDays['max'] ?? $location->delivery_days_max);
        }

        $methodData = [
            'id' => $method->id,
            'name' => $method->name,
            'base_price' => (float) $basePrice,
            'free_delivery_threshold' => $freeThreshold !== null ? (float) $freeThreshold : null,
            'delivery_days_min' => $deliveryDaysMin,
            'delivery_days_max' => $deliveryDaysMax,
            'priority' => $priority,
            'source' => $source ?? ProductRegionRuleService::SHIPPING_METHOD_SOURCE_CARRIER,
            'carrier' => null,
        ];

        // Добавляем информацию о перевозчике
        $carrier = $method->carrier;
        if ($carrier) {
            $methodData['carrier'] = [
                'id' => $carrier->id,
                'name' => $carrier->name,
                'code' => $carrier->code ?? null,
            ];
        }

        // Рассчитываем финальную цену с учетом суммы заказа
        if ($freeThreshold !== null && $orderAmount >= $freeThreshold) {
            $methodData['price'] = 0.0;
        } else {
            $methodData['price'] = (float) $basePrice;
        }

        return $methodData;
    }
}