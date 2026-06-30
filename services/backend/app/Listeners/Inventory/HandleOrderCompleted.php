<?php

namespace App\Listeners\Inventory;

use App\Events\OrderCompleted;
use App\Services\Inventory\StockService;

/**
 * Слушатель события завершения заказа
 * Обновляет статистику продаж при завершении заказа
 *
 * Выполняется синхронно для гарантии обновления статистики
 */
class HandleOrderCompleted
{

    public function __construct(
        protected StockService $stockService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->getOrder();

        // Загружаем связи
        $order->load(['items.product']);

        // Обновляем статистику продаж
        $this->stockService->updateSalesStatistics($order);

        // Синхронизация с внешней системой
        if ($this->stockService->externalSync ?? null) {
            try {
                $this->stockService->externalSync->syncOrderCompleted($order);
            } catch (\Exception $e) {
                \Log::error("Failed to sync completed order", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
