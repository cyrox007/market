<?php

namespace App\Services\Shipping;

use App\Models\Shipping\ShippingLocation;
use App\Models\Shipping\DeliveryHandlingType;
use App\Services\Shipping\Contracts\ShippingCostCalculatorInterface;
use App\Services\Shipping\Contracts\DeliveryHandlingProviderInterface;
use App\Services\Shipping\DTO\ShippingCalculationResult;
use Illuminate\Support\Facades\Log;
use Vanilo\Shipment\Models\ShippingMethod;
use App\Services\Shipping\ShippingCalculationService;

/**
 * Сервис для расчета стоимости доставки
 * Реализует ShippingCostCalculatorInterface
 */
class ShippingCostCalculator implements ShippingCostCalculatorInterface
{
    public function __construct(
        private readonly CarrierService $carrierService,
        private readonly ShippingCalculationService $shippingCalculationService,
        private readonly ?DeliveryHandlingProviderInterface $deliveryHandlingProvider = null
    ) {
    }

    /**
     * Рассчитать стоимость доставки для локации
     */
    public function calculateForLocation(
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?DeliveryHandlingType $handlingType = null,
        ?int $floor = null,
        bool $requiresAssembly = false
    ): ShippingCalculationResult {
        // Получаем эффективную стоимость доставки с учетом наследования
        $deliveryPrice = $location->getEffectiveDeliveryPrice() ?? 0.0;
        $basePrice = $deliveryPrice;

        // Проверяем порог бесплатной доставки
        $freeDeliveryThreshold = $location->getEffectiveFreeDeliveryThreshold();
        if ($freeDeliveryThreshold !== null && $orderAmount >= $freeDeliveryThreshold) {
            $deliveryPrice = 0.0;
        }

        // Рассчитываем стоимость обработки доставки (разгрузка, подъем) через интерфейс
        $handlingPrice = null;
        if ($handlingType) {
            $provider = $this->deliveryHandlingProvider ?? app(DeliveryHandlingProviderInterface::class);
            $handlingPrice = $provider->getHandlingPrice($location, $handlingType, $floor);
        }

        // Получаем стоимость сборки
        $assemblyPrice = null;
        if ($requiresAssembly) {
            $assemblyPrice = $location->getEffectiveAssemblyPrice();
        }

        // Сроки доставки: единообразно через наследование локации
        $deliveryDays = $location->getEffectiveDeliveryDays();
        $deliveryDaysMin = $deliveryDays['min'] ?? null;
        $deliveryDaysMax = $deliveryDays['max'] ?? null;

        Log::debug('ShippingCostCalculator::calculateForLocation', [
            'location_id' => $location->id,
            'delivery_price_effective' => $deliveryPrice,
            'delivery_days_min' => $deliveryDaysMin,
            'delivery_days_max' => $deliveryDaysMax,
        ]);

        // Получаем сроки сборки
        $assemblyDays = $location->getEffectiveAssemblyDays();

        return new ShippingCalculationResult(
            deliveryPrice: $deliveryPrice,
            handlingPrice: $handlingPrice,
            assemblyPrice: $assemblyPrice,
            freeDeliveryThreshold: $freeDeliveryThreshold,
            deliveryDays: $deliveryDays,
            deliveryDaysMin: $deliveryDaysMin,
            deliveryDaysMax: $deliveryDaysMax,
            assemblyDays: $assemblyDays,
            requiresAssembly: $requiresAssembly || $location->getEffectiveRequiresAssembly(),
            basePrice: $basePrice,
        );
    }

    /**
     * Рассчитать стоимость доставки для конкретного метода доставки
     */
    public function calculateForMethod(
        ShippingMethod $shippingMethod,
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?DeliveryHandlingType $handlingType = null,
        ?int $floor = null,
        bool $requiresAssembly = false
    ): ShippingCalculationResult {
        // Получаем конфигурацию метода доставки
        $config = $shippingMethod->configuration ?? [];
        $basePrice = isset($config['base_price'])
            ? (float) $config['base_price']
            : (float) ($location->getEffectiveDeliveryPrice() ?? 0.0);
        $freeThreshold = array_key_exists('free_delivery_threshold', $config)
            ? $config['free_delivery_threshold']
            : $location->getEffectiveFreeDeliveryThreshold();
        $effectiveDays = $location->getEffectiveDeliveryDays();
        $deliveryDaysMin = $config['delivery_days_min'] ?? ($effectiveDays['min'] ?? null) ?? 1;
        $deliveryDaysMax = $config['delivery_days_max'] ?? ($effectiveDays['max'] ?? null) ?? 3;

        // Рассчитываем стоимость доставки
        $deliveryPrice = $this->carrierService->calculateShippingMethodPrice(
            $shippingMethod,
            $location,
            $orderAmount
        );

        // Рассчитываем стоимость обработки доставки (разгрузка, подъем) через интерфейс
        $handlingPrice = null;
        if ($handlingType) {
            $provider = $this->deliveryHandlingProvider ?? app(DeliveryHandlingProviderInterface::class);
            $handlingPrice = $provider->getHandlingPrice($location, $handlingType, $floor);
        }

        // Получаем стоимость сборки
        $assemblyPrice = null;
        if ($requiresAssembly) {
            $assemblyPrice = $location->getEffectiveAssemblyPrice();
        }

        // Получаем сроки доставки
        $deliveryDays = [
            'min' => $deliveryDaysMin,
            'max' => $deliveryDaysMax,
        ];

        // Получаем сроки сборки
        $assemblyDays = $location->getEffectiveAssemblyDays();

        Log::debug('ShippingCostCalculator::calculateForMethod', [
            'shipping_method_id' => $shippingMethod->id,
            'location_id' => $location->id,
            'source' => $config['source'] ?? null,
            'base_price_config' => $basePrice,
        ]);

        return new ShippingCalculationResult(
            deliveryPrice: $deliveryPrice,
            handlingPrice: $handlingPrice,
            assemblyPrice: $assemblyPrice,
            freeDeliveryThreshold: $freeThreshold,
            deliveryDays: $deliveryDays,
            deliveryDaysMin: $deliveryDaysMin,
            deliveryDaysMax: $deliveryDaysMax,
            assemblyDays: $assemblyDays,
            requiresAssembly: $requiresAssembly,
            basePrice: $basePrice,
        );
    }

    /**
     * Проверить возможность доставки в локацию
     */
    public function checkDeliveryAvailability(
        ShippingLocation $location,
        float $orderAmount = 0.0,
        ?float $orderWeight = null,
        ?float $orderVolume = null
    ): array {
        return $this->shippingCalculationService->checkDeliveryAvailability(
            $location,
            $orderAmount,
            $orderWeight,
            $orderVolume
        );
    }
}
