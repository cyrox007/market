<?php

namespace App\Listeners\Inventory;

use App\Actions\Inventory\Stock\IncreaseStockAction;
use App\Events\OrderCancelled;

/**
 * Слушатель события отмены заказа
 * Возвращает остатки товаров при отмене заказа
 *
 * Выполняется синхронно для гарантии возврата остатков
 */
class HandleOrderCancelled
{

    public function __construct(
        protected IncreaseStockAction $increaseStockAction
    ) {
    }

    /**
     * Handle the event.
     * Защита от повторной обработки через статический кеш
     */
    public function handle(OrderCancelled $event): void
    {
        static $processedOrders = [];

        $order = $event->getOrder();
        $orderId = $order->id;
        $cacheKey = 'cancelled_' . $orderId;

        // Проверяем, был ли заказ уже обработан
        if (isset($processedOrders[$cacheKey])) {
            \Log::warning("HandleOrderCancelled: Order already processed, skipping", [
                'order_id' => $orderId,
                'order_number' => $order->number,
            ]);
            return;
        }

        try {
            \Log::info("HandleOrderCancelled: Processing cancelled order", [
                'order_id' => $orderId,
                'order_number' => $order->number,
            ]);

            // Загружаем связи
            $order->load(['items.product']);

            \Log::info("HandleOrderCancelled: Order loaded with items", [
                'order_id' => $orderId,
                'items_count' => $order->items->count(),
            ]);

            // Возвращаем остатки
            $results = $this->increaseStockAction->execute($order);

            // Помечаем заказ как обработанный
            $processedOrders[$cacheKey] = true;

            \Log::info("HandleOrderCancelled: Stock increased", [
                'order_id' => $orderId,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            \Log::error("HandleOrderCancelled: Error processing cancelled order", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
