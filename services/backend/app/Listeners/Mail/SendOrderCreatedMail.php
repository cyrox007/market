<?php

namespace App\Listeners\Mail;

use App\Events\OrderCreated;
use App\Mail\OrderCreatedMail;
use App\Services\Mail\MailEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события создания заказа.
 * Отправляет письмо о создании заказа (в очереди, чтобы не блокировать создание заказа).
 */
class SendOrderCreatedMail implements ShouldQueue
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->getOrder();

            // Проверяем, есть ли email для отправки
            if (!$order->contact_email) {
                Log::warning("SendOrderCreatedMail: No email for order", [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                ]);
                return;
            }

            // Подготавливаем переменные для шаблона
            $variables = $this->prepareVariables($order);

            // Создаем Mailable
            $mailable = new OrderCreatedMail($order);

            // Отправляем письмо через сервис
            $this->mailEventService->sendMailable(
                'order.created',
                $order->contact_email,
                $mailable,
                $variables
            );

            Log::info("SendOrderCreatedMail: Mail sent for order", [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'email' => $order->contact_email,
            ]);
        } catch (\Exception $e) {
            Log::error("SendOrderCreatedMail: Error sending mail", [
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
            'order_subtotal' => number_format((float) $order->subtotal, 2, '.', ' ') . ' ₽',
            'order_delivery_cost' => number_format((float) $order->delivery_cost, 2, '.', ' ') . ' ₽',
            'order_assembly_cost' => number_format((float) $order->assembly_cost, 2, '.', ' ') . ' ₽',
            'order_status' => $order->getStatusLabel(),
            'contact_name' => $order->contact_name,
            'contact_phone' => $order->contact_phone,
            'contact_email' => $order->contact_email,
            'delivery_type' => $order->delivery_type === 'delivery' ? 'Доставка' : 'Самовывоз',
            'payment_method' => $this->getPaymentMethodLabel($order->payment_method),
            'order_date' => $order->created_at->format('d.m.Y H:i'),
        ];
    }

    /**
     * Get payment method label.
     */
    protected function getPaymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'card' => 'Карта',
            'cash' => 'Наличные',
            'installment' => 'Рассрочка',
            default => $method ?? 'Не указано',
        };
    }
}
