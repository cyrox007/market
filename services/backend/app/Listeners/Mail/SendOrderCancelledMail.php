<?php

namespace App\Listeners\Mail;

use App\Events\OrderCancelled;
use App\Mail\OrderCancelledMail;
use App\Services\Mail\MailEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события отмены заказа.
 * Отправляет письмо об отмене заказа (в очереди).
 */
class SendOrderCancelledMail implements ShouldQueue
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        try {
            $order = $event->getOrder();

            // Проверяем, есть ли email для отправки
            if (!$order->contact_email) {
                Log::warning("SendOrderCancelledMail: No email for order", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return;
            }

            // Подготавливаем переменные для шаблона
            $variables = $this->prepareVariables($order);

            // Создаем Mailable
            $mailable = new OrderCancelledMail($order);

            // Отправляем письмо через сервис
            $this->mailEventService->sendMailable(
                'order.cancelled',
                $order->contact_email,
                $mailable,
                $variables
            );

            Log::info("SendOrderCancelledMail: Mail sent for order", [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'email' => $order->contact_email,
            ]);
        } catch (\Exception $e) {
            Log::error("SendOrderCancelledMail: Error sending mail", [
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
    protected function prepareVariables($order): array
    {
        return [
            'order_number' => $order->number,
            'order_id' => $order->id,
            'order_total' => number_format((float) $order->total, 2, '.', ' ') . ' ₽',
            'contact_name' => $order->contact_name,
            'order_date' => $order->created_at->format('d.m.Y H:i'),
        ];
    }
}
