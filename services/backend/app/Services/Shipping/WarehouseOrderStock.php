<?php

namespace App\Services\Shipping;

use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Order\Order;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseOrderStock
{
    /** Called inside the order transaction, before payment is initialized. */
    public function reserve(Order $order): void
    {
        $quote = $order->fulfillment_snapshot;
        if (! $quote || isset($quote['stock_state'])) {
            return;
        }
        if (($quote['stock_mode'] ?? null) === 'multi') {
            $order->update(['fulfillment_snapshot' => app(MultiWarehouseBundleStock::class)->reserve($order, $quote)]);
            return;
        }
        $warehouse = \App\Models\Inventory\Warehouse::lockForUpdate()->find($quote['warehouse_id']);
        if ($warehouse?->physical_site_id) {
            $warehouse->setRelation('physicalSite', \App\Models\Inventory\PhysicalSite::lockForUpdate()->find($warehouse->physical_site_id));
        }
        if (! $warehouse?->is_active || $warehouse->stock_mode !== $quote['stock_mode']) {
            throw ValidationException::withMessages(['delivery' => 'Условия доставки изменились. Пересчитайте заказ.']);
        }
        if (isset($quote['warehouse_fingerprint']) && hash('sha256', json_encode([$warehouse->getAttributes(), $warehouse->physicalSite?->getAttributes()])) !== $quote['warehouse_fingerprint']) {
            throw ValidationException::withMessages(['delivery' => 'Параметры склада изменились. Пересчитайте заказ.']);
        }
        if (($quote['mode'] ?? null) === 'pickup') {
            $store = ! empty($quote['store_id']) ? \App\Models\Page\Store::lockForUpdate()->find($quote['store_id']) : null;
            $missingStore = ! empty($quote['store_id']) && ! $store;
            $changedStore = $store && isset($quote['store_fingerprint'])
                && hash('sha256', json_encode($store->getAttributes())) !== $quote['store_fingerprint'];
            if ($missingStore || $changedStore || ! app(PickupReadiness::class)->ready($warehouse, $store)) {
                throw ValidationException::withMessages(['warehouse_id' => 'Самовывоз в выбранной точке больше недоступен.']);
            }
        } else {
            $profile = \App\Models\Shipping\WarehouseDeliveryProfile::lockForUpdate()->find($quote['profile_id']);
            if (! $profile?->is_active || $profile->warehouse_id !== $warehouse->id
                || hash('sha256', json_encode($profile->getAttributes())) !== ($quote['profile_fingerprint'] ?? null)) {
                throw ValidationException::withMessages(['delivery' => 'Условия доставки изменились. Пересчитайте заказ.']);
            }
        }
        if ($quote['stock_mode'] === 'availability') {
            foreach ($quote['items'] as $item) {
                $availability = $warehouse->productAvailabilities()->where('product_id', $item['product_id'])->lockForUpdate()->first();
                if (! $availability?->available_to_order) {
                    throw ValidationException::withMessages(['cart' => 'Товар больше недоступен под заказ.']);
                }
            }
        }
        if ($quote['stock_mode'] === 'quantity') {
            foreach (collect($quote['items'])->sortBy('product_id') as $item) {
                $stock = ProductWarehouseStock::where('warehouse_id', $quote['warehouse_id'])
                    ->where('product_id', $item['product_id'])->lockForUpdate()->first();
                if (! $stock || app(\App\Services\Inventory\CartStockReservations::class)->available($stock->warehouse_id, $stock->product_id, (float) $stock->quantity) < $item['quantity']) {
                    throw ValidationException::withMessages(['cart' => 'Остатки выбранного склада изменились. Пересчитайте доставку.']);
                }
                if ($order->cooling_until) {
                    $stock->increment('reserved_quantity', $item['quantity']);
                    $quote['reservation_version'] = 2;
                } else {
                    $stock->decrement('quantity', $item['quantity']);
                }
                app(\App\Services\Inventory\CartStockReservations::class)->release((int) $item['product_id']);
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                if (! $order->cooling_until && $product->stock !== null) {
                    $quote['global_stock_deltas'][$item['product_id']] = min(max(0, (float) $product->stock), $item['quantity']);
                    $product->update(['stock' => max(0, (float) $product->stock - $item['quantity'])]);
                }
            }
        }
        $quote['stock_state'] = 'reserved';
        $order->update(['fulfillment_snapshot' => $quote]);
    }

    public function release(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $quote = $locked->fulfillment_snapshot;
            if (($quote['stock_state'] ?? null) !== 'reserved') {
                return [];
            }
            if (($quote['stock_mode'] ?? null) === 'multi') {
                $quote = app(MultiWarehouseBundleStock::class)->release($quote);
                $locked->update(['fulfillment_snapshot' => $quote]);
                return collect($quote['warehouse_allocations'])->map(fn (array $source): array => [
                    'success' => true, 'warehouse_id' => $source['warehouse_id'],
                ])->all();
            }
            if ($quote['stock_mode'] === 'quantity') {
                foreach (collect($quote['items'])->sortBy('product_id') as $item) {
                    $stock = ProductWarehouseStock::where('warehouse_id', $quote['warehouse_id'])
                        ->where('product_id', $item['product_id'])->lockForUpdate()->firstOrFail();
                    if (($quote['reservation_version'] ?? 1) === 2) {
                        $stock->decrement('reserved_quantity', $item['quantity']);
                    } else {
                        $stock->increment('quantity', $item['quantity']);
                    }
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                    if (($quote['reservation_version'] ?? 1) === 1 && $product->stock !== null) {
                        $product->increment('stock', $quote['global_stock_deltas'][$item['product_id']] ?? 0);
                    }
                }
            }
            $quote['stock_state'] = 'released';
            $locked->update(['fulfillment_snapshot' => $quote]);

            return [['success' => true, 'warehouse_id' => $quote['warehouse_id']]];
        });
    }

    public function consume(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $quote = $locked->fulfillment_snapshot;
            if (($quote['stock_state'] ?? null) !== 'reserved' || ($quote['reservation_version'] ?? 1) !== 2) { return; }
            if (($quote['stock_mode'] ?? null) === 'multi') {
                $locked->update(['fulfillment_snapshot' => app(MultiWarehouseBundleStock::class)->consume($quote)]);
                return;
            }
            foreach (collect($quote['items'])->sortBy('product_id') as $item) {
                $stock = ProductWarehouseStock::where('warehouse_id', $quote['warehouse_id'])->where('product_id', $item['product_id'])->lockForUpdate()->firstOrFail();
                if ($stock->quantity < $item['quantity'] || $stock->reserved_quantity < $item['quantity']) {
                    throw ValidationException::withMessages(['stock' => 'Остаток/резерв изменился. Требуется сверка склада.']);
                }
                $stock->decrement('quantity', $item['quantity']);
                $stock->decrement('reserved_quantity', $item['quantity']);
            }
            $quote['stock_state'] = 'consumed';
            $locked->update(['fulfillment_snapshot' => $quote]);
        });
    }
}
