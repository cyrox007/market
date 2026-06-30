<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\Carrier;
use App\Services\Product\ProductRegionRuleService;
use Vanilo\Shipment\Models\ShippingMethod;

/**
 * Сервис для работы с carriers и создания shipping methods на основе наследования
 */
class CarrierService
{
    /**
     * Создать или получить демонстрационные carriers
     */
    public function createDemoCarriers(): array
    {
        $carriers = [];

        // Стандартная доставка
        $carriers['standard'] = Carrier::firstOrCreate(
            ['name' => 'Стандартная доставка'],
            [
                'name' => 'Стандартная доставка',
                'is_active' => true,
                'configuration' => [
                    'type' => 'standard',
                    'description' => 'Обычная доставка курьером',
                ],
            ]
        );

        // Экспресс-доставка
        $carriers['express'] = Carrier::firstOrCreate(
            ['name' => 'Экспресс-доставка'],
            [
                'name' => 'Экспресс-доставка',
                'is_active' => true,
                'configuration' => [
                    'type' => 'express',
                    'description' => 'Быстрая доставка в день заказа',
                ],
            ]
        );

        // Доставка в регионы
        $carriers['regional'] = Carrier::firstOrCreate(
            ['name' => 'Доставка в регионы'],
            [
                'name' => 'Доставка в регионы',
                'is_active' => true,
                'configuration' => [
                    'type' => 'regional',
                    'description' => 'Доставка по всей России через транспортные компании',
                ],
            ]
        );

        return $carriers;
    }

    /**
     * Создать shipping methods для локации на основе carriers с учетом наследования
     *
     * @param ShippingLocation $location
     * @return array Массив созданных shipping methods
     */
    public function createShippingMethodsForLocation(ShippingLocation $location): array
    {
        return app(ProductRegionRuleService::class)
            ->getAvailableShippingMethods($location)
            ->all();
    }

    /**
     * Получить доступные shipping methods для локации
     * Создает их автоматически, если их нет
     */
    public function getAvailableShippingMethods(ShippingLocation $location): \Illuminate\Support\Collection
    {
        return app(ProductRegionRuleService::class)
            ->getAvailableShippingMethods($location);
    }

    /**
     * Рассчитать стоимость доставки для конкретного shipping method
     */
    public function calculateShippingMethodPrice(
        ShippingMethod $shippingMethod,
        ShippingLocation $location,
        float $orderAmount = 0.0
    ): float {
        $config = $shippingMethod->configuration ?? [];
        $basePrice = isset($config['base_price'])
            ? (float) $config['base_price']
            : (float) ($location->getEffectiveDeliveryPrice() ?? 0);
        $freeThreshold = array_key_exists('free_delivery_threshold', $config)
            ? $config['free_delivery_threshold']
            : $location->getEffectiveFreeDeliveryThreshold();

        // Если достигнут порог бесплатной доставки
        if ($freeThreshold !== null && $orderAmount >= $freeThreshold) {
            return 0.0;
        }

        return (float) $basePrice;
    }
}
