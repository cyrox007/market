<?php

namespace App\Events;

use App\Models\Order\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие изменения статуса заказа
 * Вызывается при изменении статуса заказа
 */
class OrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus
    ) {
    }

    /**
     * Получить заказ
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Получить старый статус
     */
    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    /**
     * Получить новый статус
     */
    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
