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
            $enabled = $store ? $store->is_active && $store->pickup_enabled && $store->warehouse_id === $warehouse->id
                && $store->physical_site_id && $store->physical_site_id === $warehouse->physical_site_id : $warehouse->pickup_enabled;
            if (! $enabled || $warehouse->source_type !== 'physical' || $warehouse->stock_mode !== 'quantity') {
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
                if (! $stock || $stock->quantity < $item['quantity']) {
                    throw ValidationException::withMessages(['cart' => 'Остатки выбранного склада изменились. Пересчитайте доставку.']);
                }
                $stock->decrement('quantity', $item['quantity']);
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                if ($product->stock !== null) {
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
            if ($quote['stock_mode'] === 'quantity') {
                foreach (collect($quote['items'])->sortBy('product_id') as $item) {
                    $stock = ProductWarehouseStock::where('warehouse_id', $quote['warehouse_id'])
                        ->where('product_id', $item['product_id'])->lockForUpdate()->firstOrFail();
                    $stock->increment('quantity', $item['quantity']);
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                    if ($product->stock !== null) {
                        $product->increment('stock', $quote['global_stock_deltas'][$item['product_id']] ?? 0);
                    }
                }
            }
            $quote['stock_state'] = 'released';
            $locked->update(['fulfillment_snapshot' => $quote]);

            return [['success' => true, 'warehouse_id' => $quote['warehouse_id']]];
        });
    }
}
