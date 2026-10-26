<?php

namespace App\Services\Inventory;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Services\Inventory\Contracts\InventorySyncInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис управления остатками товаров
 *
 * Обеспечивает атомарные операции с остатками и синхронизацию с внешними системами
 */
class StockService
{
    public function __construct(
        protected ?InventorySyncInterface $externalSync = null,
        protected ?StockAvailabilityService $stockAvailabilityService = null,
    ) {
    }

    /**
     * Уменьшить остатки товаров для заказа
     *
     * @param Order $order
     * @return array Массив результатов для каждого товара
     */
    public function decreaseStockForOrder(Order $order): array
    {
        // Historical orders without a source snapshot must never guess a warehouse.
        if (! $order->fulfillment_snapshot) {
            return [];
        }
        DB::transaction(function () use ($order): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            app(\App\Services\Shipping\WarehouseOrderStock::class)->reserve($locked);
        });

        return [];
    }

    public function increaseStockForOrder(Order $order): array
    {
        return $order->fulfillment_snapshot
            ? app(\App\Services\Shipping\WarehouseOrderStock::class)->release($order)
            : [];
    }

    public function validateStock($items, ?int $shippingLocationId = null): array
    {
        $errors = [];

        // Преобразуем в коллекцию, если это массив
        if (is_array($items)) {
            $items = collect($items);
        }

        Log::info("validateStock: Starting validation", [
            'items_count' => $items->count(),
            'items_type' => get_class($items),
        ]);

        foreach ($items as $cartItem) {
            // Vanilo корзина возвращает объекты CartItem
            // У объекта есть свойство buyable (Product) и quantity

            // Получаем продукт из buyable
            $product = null;

            // Если это объект с методом/свойством buyable
            if (is_object($cartItem)) {
                $product = $cartItem->buyable ?? null;

                // Если buyable не доступен, пытаемся получить по product_id
                if (!$product && isset($cartItem->product_id)) {
                    $product = Product::find($cartItem->product_id);
                }
            }
            // Если это массив (на случай, если передали массив)
            elseif (is_array($cartItem)) {
                if (isset($cartItem['buyable']) && is_object($cartItem['buyable'])) {
                    $product = $cartItem['buyable'];
                } elseif (isset($cartItem['product_id'])) {
                    $product = Product::find($cartItem['product_id']);
                }
            }

            if (!$product || !($product instanceof Product)) {
                Log::warning("validateStock: Product not found", [
                    'cart_item_type' => is_object($cartItem) ? get_class($cartItem) : (is_array($cartItem) ? 'array' : gettype($cartItem)),
                    'has_buyable' => is_object($cartItem) && isset($cartItem->buyable),
                    'has_product_id' => is_object($cartItem) && isset($cartItem->product_id),
                ]);
                continue;
            }

            $availableStockResolved = $this->stockAvailabilityService?->resolveAvailableStock($product);
            if ($availableStockResolved === null) {
                Log::debug("validateStock: Product has no stock tracking", [
                    'product_id' => $product->id,
                ]);
                continue;
            }

            // Получаем количество из объекта или массива
            $requestedQuantity = is_object($cartItem)
                ? (int) ($cartItem->quantity ?? 1)
                : (int) ($cartItem['quantity'] ?? 1);

            $availableStock = (int) $availableStockResolved;
            $backorder = app(WarehouseStockResolver::class)->availableToOrder($product, null);

            Log::info("validateStock: Checking product", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'requested' => $requestedQuantity,
                'available' => $availableStock,
                'backorder' => $backorder,
            ]);

            // Если товар не позволяет backorder и остатка недостаточно
            if (!$backorder && $availableStock < $requestedQuantity) {
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'requested' => $requestedQuantity,
                    'available' => $availableStock,
                    'shortage' => $requestedQuantity - $availableStock,
                ];

                Log::warning("validateStock: Insufficient stock", [
                    'product_id' => $product->id,
                    'requested' => $requestedQuantity,
                    'available' => $availableStock,
                ]);
            }
        }

        Log::info("validateStock: Validation completed", [
            'valid' => empty($errors),
            'errors_count' => count($errors),
        ]);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Обновить статистику продаж при завершении заказа
     *
     * @param Order $order
     * @return void
     */
    public function updateSalesStatistics(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product instanceof Product) {
                continue;
            }

            $quantity = $item->quantity;

            // Используем метод addSale из интерфейса Buyable (Vanilo)
            if (method_exists($product, 'addSale')) {
                $product->addSale(now(), $quantity);

                Log::info("Sale recorded", [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'order_id' => $order->id,
                ]);
            }
        }
    }
}
