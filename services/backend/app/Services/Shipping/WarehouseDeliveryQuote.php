<?php

namespace App\Services\Shipping;

use App\Models\Inventory\Warehouse;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Address\BuildingCoordinates;
use App\Services\Address\DeliveryAddressResolver;
use App\Services\Product\ProductRegionRuleService;
use Illuminate\Validation\ValidationException;

class WarehouseDeliveryQuote
{
    public function cartLines(iterable $cartItems): array
    {
        $lines = [];
        foreach ($cartItems as $item) {
            $product = $item->buyable ?? Product::find($item->product_id ?? null);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(['cart' => 'Товар корзины больше недоступен.']);
            }
            $lines[] = ['product_id' => $product->id, 'quantity' => $item->quantity];
        }

        return $lines;
    }

    public function calculate(string $buildingId, array $lines, ?ShippingLocation $priceRegion = null): array
    {
        if (! $lines) {
            throw ValidationException::withMessages(['cart' => 'Корзина пуста.']);
        }
        $address = app(DeliveryAddressResolver::class)->resolve($buildingId);
        $snapshot = $address['address_snapshot'];
        $point = app(BuildingCoordinates::class)->find($snapshot);
        $quantities = [];
        foreach ($lines as $line) {
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (! $quantity || $quantity < 1) {
                throw ValidationException::withMessages(['cart' => 'Количество должно быть положительным целым числом.']);
            }
            $id = (int) ($line['product_id'] ?? 0);
            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }
        $products = Product::whereIn('id', array_keys($quantities))->where('state', 'active')->with('parentProduct')->get()->keyBy('id');
        if ($products->count() !== count($quantities)) {
            throw ValidationException::withMessages(['cart' => 'Товар не найден.']);
        }
        $amount = 0;
        foreach ($products as $product) {
            $price = $priceRegion ? app(ProductRegionRuleService::class)->getPriceForRegion(
                $product->isVariant() ? ($product->parentProduct ?? $product) : $product,
                $priceRegion, $product->isVariant() ? $product : null) : null;
            $amount += (float) ($price ?? $product->price) * $quantities[$product->id];
        }
        $options = [];
        $warehouses = Warehouse::where('is_active', true)->with([
            'physicalSite', 'deliveryProfiles' => fn ($query) => $query->active()->orderBy('priority')->orderBy('id'),
            'productStocks' => fn ($query) => $query->whereIn('product_id', array_keys($quantities)),
            'productAvailabilities' => fn ($query) => $query->whereIn('product_id', array_keys($quantities)),
        ])->get();
        foreach ($warehouses as $warehouse) {
            $stocks = $warehouse->productStocks->keyBy('product_id');
            $availability = $warehouse->productAvailabilities->keyBy('product_id');
            $preparationMin = (int) $warehouse->processing_days_min;
            $preparationMax = (int) $warehouse->processing_days_max;
            foreach ($quantities as $id => $quantity) {
                if ($warehouse->stock_mode === 'availability') {
                    if (! ($availability[$id]->available_to_order ?? false)) {
                        continue 2;
                    }
                    $preparationMin = max($preparationMin, (int) ($availability[$id]->processing_days_min ?? 0));
                    $preparationMax = max($preparationMax, (int) ($availability[$id]->processing_days_max ?? 0));
                } elseif (($stocks[$id]->quantity ?? 0) < $quantity) {
                    continue 2;
                }
            }
            $distance = $point ? app(WarehouseDistance::class)->kilometres($warehouse, $point['latitude'], $point['longitude']) : null;
            $preparationMax = max($preparationMin, $preparationMax);
            foreach ($warehouse->deliveryProfiles as $profile) {
                if (! empty($profile->constraints)) {
                    continue; // Do not silently ignore custom constraints without an evaluator.
                }
                $territory = app(DirectoryCoverage::class)->matches($profile->directory_territories ?? [], $snapshot);
                $radius = $distance !== null && $profile->radius_km !== null && $distance <= (float) $profile->radius_km;
                $covered = match ($profile->coverage_type) {
                    'locations' => $territory, 'radius' => $radius, 'hybrid' => $territory && $radius,
                    default => false,
                };
                if (! $covered || ($profile->price_per_km > 0 && $distance === null)) {
                    continue;
                }
                $price = round((float) $profile->base_price + (float) $profile->price_per_km * ($distance ?? 0), 2);
                if ($profile->free_delivery_threshold !== null && $amount >= (float) $profile->free_delivery_threshold) {
                    $price = 0.0;
                }
                $options[] = [
                    'warehouse_id' => $warehouse->id, 'warehouse_name' => $warehouse->name,
                    'stock_mode' => $warehouse->stock_mode, 'profile_id' => $profile->id, 'profile_name' => $profile->name,
                    'profile_fingerprint' => hash('sha256', json_encode($profile->getAttributes())),
                    'delivery_cost' => $price, 'base_price' => (float) $profile->base_price,
                    'free_delivery_threshold' => $profile->free_delivery_threshold === null ? null : (float) $profile->free_delivery_threshold,
                    'transit_days_min' => (int) $profile->delivery_days_min, 'transit_days_max' => (int) $profile->delivery_days_max,
                    'processing_days_min' => $preparationMin, 'processing_days_max' => $preparationMax,
                    'delivery_days_min' => $preparationMin + (int) $profile->delivery_days_min,
                    'delivery_days_max' => $preparationMax + (int) $profile->delivery_days_max,
                    'distance_km' => $distance, 'distance_type' => 'straight_line',
                    'items' => collect($quantities)->map(fn ($quantity, $id) => ['product_id' => (int) $id, 'quantity' => $quantity])->values()->all(),
                ];
                break; // The first eligible profile by configured priority wins within a warehouse.
            }
        }
        $options = collect($options)->sortBy([['delivery_cost', 'asc'], ['delivery_days_max', 'asc'], ['warehouse_id', 'asc']])->values()->all();

        return [
            'available' => count($options) > 0, 'currency' => 'RUB', 'subtotal' => round($amount, 2),
            'address' => $address, 'selected' => $options[0] ?? null, 'options' => $options,
            'reason' => $options ? null : 'Нет склада, который обеспечивает всю корзину и доставляет по этому адресу. Проверьте покрытие и координаты для радиусного тарифа.',
        ];
    }
}
