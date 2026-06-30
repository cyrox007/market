<?php

namespace App\Listeners\Inventory;

use App\Actions\Inventory\Stock\DecreaseStockAction;
use App\Events\OrderCreated;

/**
 * Слушатель события создания заказа
 * Уменьшает остатки товаров при создании заказа
 *
 * Выполняется синхронно для гарантии изменения остатков
 */
class HandleOrderCreated
{

    public function __construct(
        protected DecreaseStockAction $decreaseStockAction
    ) {
    }

    /**
     * Handle the event.
     * Защита от повторной обработки через статический кеш
     */
    public function handle(OrderCreated $event): void
    {
        static $processedOrders = [];

        $order = $event->getOrder();
        $orderId = $order->id;

        // Проверяем, был ли заказ уже обработан
        if (isset($processedOrders[$orderId])) {
            \Log::warning("HandleOrderCreated: Order already processed, skipping", [
                'order_id' => $orderId,
                'order_number' => $order->number,
            ]);
            return;
        }

        try {
            \Log::info("HandleOrderCreated: Processing order", [
                'order_id' => $orderId,
                'order_number' => $order->number,
            ]);

            // Загружаем связи
            $order->load(['items.product']);

            \Log::info("HandleOrderCreated: Order loaded with items", [
                'order_id' => $orderId,
                'items_count' => $order->items->count(),
            ]);

            // Уменьшаем остатки
            $results = $this->decreaseStockAction->execute($order);

            // Помечаем заказ как обработанный
            $processedOrders[$orderId] = true;

            \Log::info("HandleOrderCreated: Stock decreased", [
                'order_id' => $orderId,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            \Log::error("HandleOrderCreated: Error processing order", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
