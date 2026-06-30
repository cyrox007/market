<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Support\Integration\OrderOneCSyncDispatcher;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Payment\Gateways\RaiffeisenAcquiringGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Vanilo\Payment\Models\PaymentProxy;
use Vanilo\Payment\Processing\PaymentResponseHandler;

/**
 * Обработка callback от Райффайзен в очереди.
 * Контроллер сразу возвращает 200, чтобы банк не повторял запрос; разбор и обновление — здесь.
 */
class ProcessRaiffeisenCallbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param array<string, mixed> $payload Тело callback (orderId, status, и т.д.)
     */
    public function __construct(
        private readonly array $payload
    ) {
        $this->queue = 'default';
    }

    public function handle(GatewayLoggerInterface $gatewayLog): void
    {
        $requestIp = $this->payload['_request_ip'] ?? null;
        $payloadClean = array_diff_key($this->payload, array_flip(['_request_ip', '_request_user_agent']));

        $orderId = $this->payload['orderId'] ?? $this->payload['order_id'] ?? $this->payload['id'] ?? null;
        if (empty($orderId)) {
            Log::warning('ProcessRaiffeisenCallbackJob: missing orderId', ['payload_keys' => array_keys($payloadClean)]);
            $gatewayLog->log('raiffeisen_acquiring', 'callback_rejected', 'Callback без orderId', [
                'payload_keys' => array_keys($payloadClean),
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
            return;
        }

        $order = Order::where('number', (string) $orderId)->first();
        if (!$order) {
            Log::warning('ProcessRaiffeisenCallbackJob: order not found', ['orderId' => $orderId]);
            $gatewayLog->log('raiffeisen_acquiring', 'callback_rejected', 'Заказ не найден: ' . $orderId, [
                'order_number' => $orderId,
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
            return;
        }

        $payment = PaymentProxy::modelClass()::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            Log::warning('ProcessRaiffeisenCallbackJob: payment not found for order', ['orderId' => $orderId]);
            $gatewayLog->log('raiffeisen_acquiring', 'callback_rejected', 'Платёж не найден для заказа ' . $order->number, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order, 'payment', 'warning');
            return;
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof RaiffeisenAcquiringGateway) {
            Log::warning('ProcessRaiffeisenCallbackJob: invalid gateway for payment', ['payment_id' => $payment->id]);
            return;
        }

        $gatewayLog->log('raiffeisen_acquiring', 'callback_received', 'Получен callback по заказу ' . $order->number, [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'amount' => (float) $payment->getAmount(),
            'contact_email' => $order->contact_email,
            'ip' => $requestIp,
        ], $payment, 'payment', 'info');

        $request = Request::create('/', 'POST', [], [], [], [], json_encode($payloadClean));
        $request->headers->set('Content-Type', 'application/json');

        $response = $gateway->processPaymentResponse($request, ['payment' => $payment]);

        $handler = new PaymentResponseHandler($payment, $response);
        $handler->writeResponseToHistory();
        $handler->updatePayment();

        if ($response->getTransactionId()) {
            $payment->remote_id = $response->getTransactionId();
            $payment->save();
        }
        if ($order instanceof \Vanilo\Contracts\Payable && $response->getTransactionId()) {
            $order->setPayableRemoteId($response->getTransactionId());
        }

        $handler->fireEvents();

        if ($response->wasSuccessful()) {
            $gatewayLog->log('raiffeisen_acquiring', 'callback_success', 'Оплата получена: заказ ' . $order->number . ', сумма ' . (float) $payment->getAmount() . ' ₽', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => (float) $payment->getAmount(),
                'remote_id' => $response->getTransactionId(),
                'contact_email' => $order->contact_email,
                'ip' => $requestIp,
            ], $payment, 'payment', 'info');

            if (in_array($order->status, [OrderStatus::NEW->value, OrderStatus::AWAITING_PAYMENT->value], true)) {
                $order->changeStatus(
                    OrderStatus::ACCEPTED,
                    'Оплата получена (Райффайзен)',
                    null
                );
            } else {
                OrderOneCSyncDispatcher::dispatch((int) $order->id);
            }
        } else {
            $gatewayLog->log('raiffeisen_acquiring', 'callback_failed', 'Оплата не прошла: заказ ' . $order->number . ' — ' . ($response->getMessage() ?? 'отклонено'), [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => (float) $payment->getAmount(),
                'message' => $response->getMessage(),
                'contact_email' => $order->contact_email,
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessRaiffeisenCallbackJob failed: ' . $e->getMessage(), [
            'payload' => $this->payload,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
