<?php

namespace App\Services\Product;

use App\Models\Payment\PaymentMethod;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Collection;

/**
 * Compatibility facade for pricing, shipping and payment methods.
 * Retired product_region_rules no longer affect the public catalogue.
 */
class ProductRegionRuleService
{
    public const SHIPPING_METHOD_SOURCE_CARRIER = 'carrier';

    public const SHIPPING_METHOD_SOURCE_LOCATION_FALLBACK = 'location_fallback';

    /**
     * Compatibility method retained for callers during the removal of the
     * legacy per-product regional rules. No historical rule is loaded.
     */
    public function preloadRulesForLocation(?ShippingLocation $location): void
    {
        // Old product_region_rules are archived but never consulted.
    }

    /**
     * One source of truth for SKU prices: current pricing policies and
     * private offers. Warehouse/delivery charges are calculated separately.
     */
    public function getPriceForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): float
    {
        return app(\App\Services\Pricing\PricingEngine::class)
            ->price($variant ?? $product, $location);
    }

    /**
     * Compatibility for callers requesting a "legacy" base: plain SKU price,
     * never the obsolete per-product location overrides.
     */
    public function getLegacyPriceForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): float
    {
        return (float) ($variant ?? $product)->price;
    }

    /**
     * Historical region hiding is retired. Product activity, SKU availability
     * and destination-specific warehouse fulfilment remain authoritative.
     */
    public function isVisibleInRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): bool
    {
        return true;
    }

    /**
     * Получить срок доставки для товара в локации доставки
     * 
     * @param Product $product Товар
     * @param ShippingLocation|null $location Локация доставки (null = без локации)
     * @param Product|null $variant Вариация товара (если есть)
     * @return int|null Срок доставки в днях (null если не переопределен)
     */
    public function getDeliveryDaysForRegion(Product $product, ?ShippingLocation $location, ?Product $variant = null): ?int
    {
        $destination = request()->attributes->get('warehouse_destination');
        if (! $destination || ! $destination['specified']) {
            return null;
        }
        $sources = app(\App\Services\Shipping\FulfillmentSourceResolver::class)->resolve($variant ?? $product, $destination);
        return $sources->map(fn ($source): int => $source['processing_days_min'] + $source['delivery_days_min'])->min();

    }

    /**
     * Получить доступные методы доставки для локации доставки
     * Берет carriers из локации (с учетом иерархии) и создает ShippingMethod на их основе
     * 
     * @param ShippingLocation|null $location Локация доставки (null = все методы)
     * @return Collection Коллекция методов доставки
     */
    public function getAvailablePaymentMethods(?ShippingLocation $location): Collection
    {
        // Если локация не указана, возвращаем все активные методы
        if (!$location) {
            return PaymentMethod::active()->ordered()->get();
        }

        // Получаем методы оплаты для локации с учетом иерархии
        $paymentMethods = $location->getEffectivePaymentMethods();

        if ($paymentMethods->isEmpty()) {
            // Если методов оплаты нет, возвращаем все активные методы (fallback)
            return PaymentMethod::active()->ordered()->get();
        }

        // Сортируем по sort_order из pivot
        return $paymentMethods->sortBy(function ($method) use ($location) {
            // Сначала ищем sort_order в текущей локации, затем у предков.
            $locationIds = $location->getAncestorsIds();
            
            foreach ($locationIds as $locId) {
                $tempLocation = ShippingLocation::find($locId);
                if ($tempLocation) {
                    $pivot = $tempLocation->paymentMethods()
                        ->where('payment_methods.id', $method->id)
                        ->wherePivot('is_active', true)
                        ->first()?->pivot;
                    
                    if ($pivot) {
                        return $pivot->sort_order ?? 999;
                    }
                }
            }
            
            return $method->sort_order ?? 999;
        })->values();
    }
}
