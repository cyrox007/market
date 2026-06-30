<?php

namespace App\Services\Inventory;

use App\Actions\Inventory\Stock\AdjustWarehouseStockAction;
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
        protected ?AdjustWarehouseStockAction $adjustWarehouseStockAction = null,
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
        // Защита от повторной обработки - используем кеш
        static $processedOrders = [];

        if (isset($processedOrders[$order->id])) {
            Log::warning("decreaseStockForOrder: Order already processed, skipping", [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);
            return $processedOrders[$order->id];
        }

        $results = [];

        Log::info("decreaseStockForOrder: Starting", [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'items_count' => $order->items->count(),
        ]);

        DB::transaction(function () use ($order, &$results) {
            foreach ($order->items as $item) {
                Log::info("decreaseStockForOrder: Processing item", [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);

                $result = $this->decreaseStockForItem($item, $order->shipping_location_id);
                $results[] = $result;
            }
        });

        // Сохраняем результат в кеш
        $processedOrders[$order->id] = $results;

        Log::info("decreaseStockForOrder: Completed", [
            'order_id' => $order->id,
            'results_count' => count($results),
        ]);

        // Синхронизация с 1С после списания остатков (как раньше; параллельно — job из OrderCreated).
        if ($this->externalSync) {
            try {
                $this->externalSync->syncOrderCreated($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync order to external system', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Уменьшить остаток для одного элемента заказа
     *
     * @param OrderItem $item
     * @return array
     */
    public function decreaseStockForItem(OrderItem $item, ?int $shippingLocationId = null): array
    {
        $product = $item->product;

        if (!$product instanceof Product) {
            return [
                'success' => false,
                'item_id' => $item->id,
                'message' => 'Product not found',
            ];
        }

        $warehouseModeEnabled = ProductStockSettings::getInstance()->warehouse_accounting_enabled;

        // Если товар не имеет учета остатков и складской режим выключен — пропускаем
        if (!$warehouseModeEnabled && (!isset($product->stock) || $product->stock === null)) {
            return [
                'success' => true,
                'item_id' => $item->id,
                'product_id' => $product->id,
                'message' => 'Product has no stock tracking',
                'skipped' => true,
            ];
        }

        $quantity = $item->quantity;
        $oldStock = (int) ($product->stock ?? 0);
        $backorder = (bool) ($product->backorder ?? false);

        Log::info("decreaseStockForItem: Before decrease", [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'backorder' => $backorder,
        ]);

        // Проверяем, достаточно ли остатка (если backorder = false)
        if (!$backorder && $oldStock < $quantity) {
            Log::warning("Insufficient stock for product - should not happen if validation worked", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'requested' => $quantity,
                'available' => $oldStock,
                'order_item_id' => $item->id,
            ]);

            // Если остатка недостаточно и backorder=false, уменьшаем только доступное количество
            // Это защита на случай, если проверка остатков не сработала
            $actualQuantity = max(0, $oldStock);

            if ($actualQuantity > 0) {
                $product->decrement('stock', $actualQuantity);
            }
        } else {
            // Атомарное уменьшение остатка
            if ($product->stock !== null) {
                $product->decrement('stock', $quantity);
            }
        }

        $product->refresh();
        $newStock = (int) ($product->stock ?? 0);

        Log::info("Stock decreased", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'order_item_id' => $item->id,
        ]);

        $warehouseId = $this->adjustWarehouseStockAction?->decrease($product, $quantity, $shippingLocationId);

        return [
            'success' => true,
            'item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'warehouse_id' => $warehouseId,
        ];
    }

    /**
     * Увеличить остатки товаров для заказа (возврат при отмене)
     *
     * @param Order $order
     * @return array
     */
    public function increaseStockForOrder(Order $order): array
    {
        // Защита от повторной обработки - используем кеш
        static $processedCancelledOrders = [];

        $cacheKey = 'cancelled_' . $order->id;
        if (isset($processedCancelledOrders[$cacheKey])) {
            Log::warning("increaseStockForOrder: Cancelled order already processed, skipping", [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);
            return $processedCancelledOrders[$cacheKey];
        }

        $results = [];

        Log::info("increaseStockForOrder: Starting", [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'items_count' => $order->items->count(),
        ]);

        DB::transaction(function () use ($order, &$results) {
            foreach ($order->items as $item) {
                Log::info("increaseStockForOrder: Processing item", [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);

                $result = $this->increaseStockForItem($item, $order->shipping_location_id);
                $results[] = $result;
            }
        });

        // Сохраняем результат в кеш
        $processedCancelledOrders[$cacheKey] = $results;

        Log::info("increaseStockForOrder: Completed", [
            'order_id' => $order->id,
            'results_count' => count($results),
        ]);

        // Синхронизация с внешней системой
        if ($this->externalSync) {
            try {
                $this->externalSync->syncOrderCancelled($order);
            } catch (\Exception $e) {
                Log::error("Failed to sync cancelled order to external system", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Увеличить остаток для одного элемента заказа
     *
     * @param OrderItem $item
     * @return array
     */
    public function increaseStockForItem(OrderItem $item, ?int $shippingLocationId = null): array
    {
        $product = $item->product;

        if (!$product instanceof Product) {
            return [
                'success' => false,
                'item_id' => $item->id,
                'message' => 'Product not found',
            ];
        }

        $warehouseModeEnabled = ProductStockSettings::getInstance()->warehouse_accounting_enabled;

        // Если товар не имеет учета остатков и складской режим выключен — пропускаем
        if (!$warehouseModeEnabled && (!isset($product->stock) || $product->stock === null)) {
            return [
                'success' => true,
                'item_id' => $item->id,
                'product_id' => $product->id,
                'message' => 'Product has no stock tracking',
                'skipped' => true,
            ];
        }

        $quantity = $item->quantity;
        $oldStock = (int) ($product->stock ?? 0);

        Log::info("increaseStockForItem: Before increase", [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
        ]);

        // Атомарное увеличение остатка
        if ($product->stock !== null) {
            $product->increment('stock', $quantity);
        }
        $product->refresh();

        $newStock = (int) ($product->stock ?? 0);

        Log::info("Stock increased", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'order_item_id' => $item->id,
        ]);

        $warehouseId = $this->adjustWarehouseStockAction?->increase($product, $quantity, $shippingLocationId);

        return [
            'success' => true,
            'item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'warehouse_id' => $warehouseId,
        ];
    }

    /**
     * Проверить достаточность остатков для заказа
     *
     * @param \Illuminate\Support\Collection|array $items Коллекция или массив элементов корзины
     * @return array ['valid' => bool, 'errors' => array]
     */
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

            $availableStockResolved = $this->stockAvailabilityService?->resolveAvailableStock($product, $shippingLocationId);
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
            $backorder = (bool) ($product->backorder ?? false);

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
