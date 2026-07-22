<?php

namespace App\Services\Inventory;

use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Models\Shipping\ShippingLocation;

class WarehousePriorityResolver
{
    /**
     * Возвращает массив:
     * - nearest_stock (int)
     * - nearest_warehouse_id (int|null)
     * - nearest_warehouse_name (string|null)
     * - stocks (array) – все склады с количеством, отсортированные по приоритету
     */
    public function resolve(Product $product, ?ShippingLocation $location): array
    {
        $settings = ProductStockSettings::getInstance();

        // Если складской учёт выключен – возвращаем общий остаток как nearest, stocks пуст
        if (!$settings->warehouse_accounting_enabled) {
            $stock = (int) ($product->getAttributes()['stock'] ?? 0);
            return [
                'nearest_stock' => $stock,
                'nearest_warehouse_id' => null,
                'nearest_warehouse_name' => null,
                'stocks' => [],
            ];
        }

        // Загружаем все остатки с привязкой к складам и их локациям
        $allStocks = $product->warehouseStocks()->with('warehouse.shippingLocations')->get();

        if ($allStocks->isEmpty()) {
            return [
                'nearest_stock' => 0,
                'nearest_warehouse_id' => null,
                'nearest_warehouse_name' => null,
                'stocks' => [],
            ];
        }

        // Если регион не задан – берём склад с максимальным остатком
        if (!$location) {
            $best = $allStocks->sortByDesc('quantity')->first();
            return [
                'nearest_stock' => (int) $best->quantity,
                'nearest_warehouse_id' => $best->warehouse_id,
                'nearest_warehouse_name' => $best->warehouse->name ?? null,
                'stocks' => $allStocks->sortByDesc('quantity')->map(fn($s) => $this->formatStock($s))->values()->toArray(),
            ];
        }

        // Получаем ID всех локаций-предков (включая саму локацию)
        $locationIds = array_unique(array_merge([$location->id], $location->getAncestorsIds()));

        // Разделяем склады на "подходящие" (привязаны к одной из локаций) и "остальные"
        $matched = collect();
        $unmatched = collect();

        foreach ($allStocks as $stock) {
            $warehouse = $stock->warehouse;
            // Получаем ID локаций, привязанных к складу
            $warehouseLocationIds = $warehouse->shippingLocations->pluck('id')->toArray();
            if (!empty(array_intersect($locationIds, $warehouseLocationIds))) {
                $matched->push($stock);
            } else {
                $unmatched->push($stock);
            }
        }

        // Сортируем matched по убыванию остатка
        $matchedSorted = $matched->sortByDesc('quantity');
        $unmatchedSorted = $unmatched->sortByDesc('quantity');

        // Берём первый из matched как nearest (если есть, иначе – первый из unmatched, или fallback)
        $nearest = $matchedSorted->first() ?? $unmatchedSorted->first();

        if (!$nearest) {
            return [
                'nearest_stock' => 0,
                'nearest_warehouse_id' => null,
                'nearest_warehouse_name' => null,
                'stocks' => [],
            ];
        }

        // Формируем финальный список: сначала matched, потом unmatched
        $finalStocks = $matchedSorted->concat($unmatchedSorted);

        return [
            'nearest_stock' => (int) $nearest->quantity,
            'nearest_warehouse_id' => $nearest->warehouse_id,
            'nearest_warehouse_name' => $nearest->warehouse->name ?? null,
            'stocks' => $finalStocks->map(fn($s) => $this->formatStock($s))->values()->toArray(),
        ];
    }

    private function formatStock($stock): array
    {
        return [
            'warehouse_id' => $stock->warehouse_id,
            'warehouse_name' => $stock->warehouse->name ?? 'Склад #' . $stock->warehouse_id,
            'quantity' => (int) $stock->quantity,
        ];
    }
}