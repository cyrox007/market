<?php

namespace App\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Inventory\Warehouse;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Shipping\WarehouseDeliveryProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atomic movement of real component SKUs across several warehouse sources.
 *
 * Called while the Order transaction is open. The single fulfillment_snapshot
 * contains an immutable allocation for each line and source. Sources are
 * locked in ascending warehouse+SKU order to reduce checkout deadlocks.
 */
class MultiWarehouseBundleStock
{
    private function allocations(array $quote): array
    {
        $allocations = $quote['warehouse_allocations'] ?? [];
        if (count($allocations) < 2 || ! is_array($allocations)) {
            throw ValidationException::withMessages(['delivery' => 'Не указан корректный состав складской отгрузки.']);
        }
        usort($allocations, fn ($a, $b) => ((int) $a['warehouse_id']) <=> ((int) $b['warehouse_id']));
        return $allocations;
    }

    private function verifySource(array $source): Warehouse
    {
        $warehouse = Warehouse::lockForUpdate()->find($source['warehouse_id']);
        if ($warehouse?->physical_site_id) {
            $warehouse->setRelation('physicalSite', \App\Models\Inventory\PhysicalSite::lockForUpdate()
                ->find($warehouse->physical_site_id));
        }
        if (! $warehouse?->is_active || $warehouse->stock_mode !== $source['stock_mode']
            || hash('sha256', json_encode([$warehouse->getAttributes(),
                $warehouse->physicalSite?->getAttributes()])) !== ($source['warehouse_fingerprint'] ?? null)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Один из складов изменился. Пересчитайте доставку.']);
        }
        $profile = WarehouseDeliveryProfile::lockForUpdate()->find($source['profile_id']);
        if (! $profile?->is_active || (int) $profile->warehouse_id !== (int) $warehouse->id
            || hash('sha256', json_encode($profile->getAttributes())) !== ($source['profile_fingerprint'] ?? null)) {
            throw ValidationException::withMessages(['delivery' => 'Условия доставки с одного из складов изменились.']);
        }

        return $warehouse;
    }

    private function lines(array $allocations): array
    {
        $seen = [];
        $result = [];
        foreach ($allocations as $allocation) {
            foreach ($allocation['items'] ?? [] as $line) {
                $skuId = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                if ($skuId < 1 || $qty < 1 || isset($seen[$skuId])) {
                    throw ValidationException::withMessages(['cart' => 'Некорректные складские позиции комплекта.']);
                }
                $seen[$skuId] = true;
                $result[] = [
                    'warehouse_id' => (int) $allocation['warehouse_id'],
                    'stock_mode' => $allocation['stock_mode'],
                    'product_id' => $skuId, 'quantity' => $qty,
                ];
            }
        }
        return $result;
    }

    public function reserve(Order $order, array $quote): array
    {
        $allocations = $this->allocations($quote);
        $sources = [];
        foreach ($allocations as $source) {
            $warehouse = $this->verifySource($source);
            $sources[$warehouse->id] = $warehouse;
        }
        $lines = $this->lines($allocations);
        // Confirm the plan still accounts for every component, no extras.
        $planned = collect($lines)->pluck('quantity', 'product_id')->sortKeys()->all();
        $requested = collect($quote['items'])->pluck('quantity', 'product_id')->sortKeys()->all();
        if ($planned !== $requested) {
            throw ValidationException::withMessages(['cart' => 'Состав складской отгрузки не совпадает с заказом.']);
        }

        // Validate + lock ALL stock rows before updating any one of them.
        $stocks = [];
        foreach ($lines as $line) {
            $id = $line['warehouse_id'];
            if ($line['stock_mode'] === 'availability') {
                $availability = $sources[$id]->productAvailabilities()
                    ->where('product_id', $line['product_id'])->lockForUpdate()->first();
                if (! $availability?->available_to_order) {
                    throw ValidationException::withMessages(['cart' => 'Один из компонентов больше недоступен под заказ.']);
                }
                continue;
            }
            $stock = ProductWarehouseStock::where('warehouse_id', $id)
                ->where('product_id', $line['product_id'])->lockForUpdate()->first();
            if (! $stock || app(\App\Services\Inventory\CartStockReservations::class)
                ->available($id, $line['product_id'], (float) $stock->quantity) < $line['quantity']) {
                throw ValidationException::withMessages(['cart' => 'Остатки составляющих на складах изменились. Обновите заказ.']);
            }
            $stocks[$id.':'.$line['product_id']] = $stock;
        }

        foreach ($lines as $line) {
            if ($line['stock_mode'] !== 'quantity') {
                continue;
            }
            $stock = $stocks[$line['warehouse_id'].':'.$line['product_id']];
            if ($order->cooling_until) {
                $stock->increment('reserved_quantity', $line['quantity']);
                $quote['reservation_version'] = 2;
            } else {
                $stock->decrement('quantity', $line['quantity']);
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);
                if ($product->stock !== null) {
                    $quote['global_stock_deltas'][$line['product_id']] =
                        min(max(0, (float) $product->stock), $line['quantity']);
                    $product->update(['stock' => max(0, (float) $product->stock - $line['quantity'])]);
                }
            }
            app(\App\Services\Inventory\CartStockReservations::class)->release($line['product_id']);
        }
        $quote['stock_state'] = 'reserved';
        return $quote;
    }

    /** Called while the order row is locked in the caller's DB transaction. */
    public function release(array $quote): array
    {
        foreach ($this->lines($this->allocations($quote)) as $line) {
            if ($line['stock_mode'] !== 'quantity') {
                continue;
            }
            $stock = ProductWarehouseStock::where('warehouse_id', $line['warehouse_id'])
                ->where('product_id', $line['product_id'])->lockForUpdate()->firstOrFail();
            if (($quote['reservation_version'] ?? 1) === 2) {
                if ($stock->reserved_quantity < $line['quantity']) {
                    throw ValidationException::withMessages(['stock' => 'Не удалось освободить резерв компонента.']);
                }
                $stock->decrement('reserved_quantity', $line['quantity']);
            } else {
                $stock->increment('quantity', $line['quantity']);
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);
                if ($product->stock !== null) {
                    $product->increment('stock', $quote['global_stock_deltas'][$line['product_id']] ?? 0);
                }
            }
        }
        $quote['stock_state'] = 'released';
        return $quote;
    }

    public function consume(array $quote): array
    {
        $lines = $this->lines($this->allocations($quote));
        $stocks = [];
        foreach ($lines as $line) {
            if ($line['stock_mode'] !== 'quantity') {
                continue;
            }
            $stock = ProductWarehouseStock::where('warehouse_id', $line['warehouse_id'])
                ->where('product_id', $line['product_id'])->lockForUpdate()->firstOrFail();
            if ($stock->quantity < $line['quantity'] || $stock->reserved_quantity < $line['quantity']) {
                throw ValidationException::withMessages(['stock' => 'Недостаточный остаток/резерв одного из компонентов.']);
            }
            $stocks[] = [$stock, $line['quantity']];
        }
        foreach ($stocks as [$stock, $qty]) {
            $stock->decrement('quantity', $qty);
            $stock->decrement('reserved_quantity', $qty);
        }
        $quote['stock_state'] = 'consumed';
        return $quote;
    }
}
