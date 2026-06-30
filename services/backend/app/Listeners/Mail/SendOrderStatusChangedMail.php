<?php

namespace App\Listeners\Mail;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusChangedMail;
use App\Services\Mail\MailEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события изменения статуса заказа.
 * Отправляет письмо об изменении статуса (в очереди).
 */
class SendOrderStatusChangedMail implements ShouldQueue
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $order = $event->getOrder();
            $oldStatus = $event->getOldStatus();
            $newStatus = $event->getNewStatus();

            // Проверяем, есть ли email для отправки
            if (!$order->contact_email) {
                Log::warning("SendOrderStatusChangedMail: No email for order", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return;
            }

            // Подготавливаем переменные для шаблона
            $variables = $this->prepareVariables($order, $oldStatus, $newStatus);

            // Создаем Mailable
            $mailable = new OrderStatusChangedMail($order, $oldStatus, $newStatus);

            // Отправляем письмо через сервис
            $this->mailEventService->sendMailable(
                'order.status_changed',
                $order->contact_email,
                $mailable,
                $variables
            );

            Log::info("SendOrderStatusChangedMail: Mail sent for order", [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'email' => $order->contact_email,
            ]);
        } catch (\Exception $e) {
            Log::error("SendOrderStatusChangedMail: Error sending mail", [
                'order_id' => $event->getOrder()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Prepare variables for template.
     *
     * @return array<string, mixed>
     */
    protected function prepareVariables($order, string $oldStatus, string $newStatus): array
    {
        return [
            'order_number' => $order->number,
            'order_id' => $order->id,
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'order_total' => number_format((float) $order->total, 2, '.', ' ') . ' ₽',
            'contact_name' => $order->contact_name,
        ];
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Новый',
            'accepted' => 'Принят',
            'assembled' => 'Собран',
            'shipped' => 'Отправлен',
            'in_transit' => 'В пути',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменен',
            default => $status,
        };
    }
}
