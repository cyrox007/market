<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Support\Integration\OrderOneCSyncDispatcher;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentRefund;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use App\Services\Payment\RaiffeisenEcomClient;
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
 * Обработка webhook от Райффайзен e-commerce API (pay.raif.ru).
 * Формат: event "PAYMENT" или "REFUND", data: { order: { id }, status, amount, refundId... }.
 */
class ProcessRaiffeisenEcomCallbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    private const GATEWAY_ID = 'raiffeisen_ecom';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload
    ) {
        $this->queue = 'default';
    }

    public function handle(GatewayLoggerInterface $gatewayLog): void
    {
        $requestIp = $this->payload['_request_ip'] ?? null;
        $signature = $this->payload['_signature'] ?? null;
        $payloadClean = array_diff_key($this->payload, array_flip(['_request_ip', '_request_user_agent', '_signature']));

        $event = $payloadClean['event'] ?? '';
        if ($event === 'REFUND') {
            $this->handleRefundEvent($payloadClean, $requestIp, $signature, $gatewayLog);
            return;
        }

        if ($event !== 'PAYMENT') {
            Log::warning('ProcessRaiffeisenEcomCallbackJob: unsupported event', [
                'event' => $event,
                'payload_keys' => array_keys($payloadClean),
            ]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Webhook с неподдерживаемым event', [
                'event' => $event,
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
            return;
        }

        $data = $payloadClean['data'] ?? [];
        $orderId = $data['order']['id'] ?? $data['orderId'] ?? null;
        if (empty($orderId)) {
            Log::warning('ProcessRaiffeisenEcomCallbackJob: missing orderId', ['payload_keys' => array_keys($payloadClean)]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Webhook без orderId', [
                'payload_keys' => array_keys($payloadClean),
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
            return;
        }

        $order = Order::where('number', (string) $orderId)->first();
        if (!$order) {
            Log::warning('ProcessRaiffeisenEcomCallbackJob: order not found', ['orderId' => $orderId]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Заказ не найден: ' . $orderId, [
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
            Log::warning('ProcessRaiffeisenEcomCallbackJob: payment not found for order', ['orderId' => $orderId]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Платёж не найден для заказа ' . $order->number, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order, 'payment', 'warning');
            return;
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof RaiffeisenEcomGateway) {
            Log::warning('ProcessRaiffeisenEcomCallbackJob: invalid gateway for payment', ['payment_id' => $payment->id]);
            return;
        }

        $method = $payment->getMethod();
        $config = $method->configuration() ?? [];
        $publicId = (string) ($config['public_id'] ?? config('payment.raiffeisen_ecom.public_id') ?? env('RAIFFEISEN_ECOM_PUBLIC_ID', ''));
        $secretKey = (string) ($config['secret_key'] ?? config('payment.raiffeisen_ecom.secret_key') ?? env('RAIFFEISEN_ECOM_SECRET_KEY', ''));
        if ($publicId === '') {
            $publicId = (string) (config('payment.raiffeisen.public_id') ?? env('RAIFFEISEN_PUBLIC_ID', ''));
        }
        if ($secretKey === '') {
            $secretKey = (string) (config('payment.raiffeisen.secret_key') ?? env('RAIFFEISEN_SECRET_KEY', ''));
        }

        if ($publicId !== '' && $secretKey !== '' && $signature) {
            $isTest = (bool) ($config['is_test'] ?? config('payment.raiffeisen_ecom.is_test', true));
            $client = new RaiffeisenEcomClient($publicId, $secretKey, $isTest);
            if (!$client->verifyPaymentSignature($signature, $payloadClean)) {
                Log::warning('ProcessRaiffeisenEcomCallbackJob: invalid signature', ['orderId' => $orderId]);
                $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Неверная подпись webhook', [
                    'order_number' => $orderId,
                    'ip' => $requestIp,
                ], null, 'payment', 'warning');
                return;
            }
        }

        $gatewayLog->log(self::GATEWAY_ID, 'callback_received', 'Получен webhook по заказу ' . $order->number, [
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
            $gatewayLog->log(self::GATEWAY_ID, 'callback_success', 'Оплата получена: заказ ' . $order->number . ', сумма ' . (float) $payment->getAmount() . ' ₽', [
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
                    'Оплата получена (Райффайзен e-commerce)',
                    null
                );
            } else {
                OrderOneCSyncDispatcher::dispatch((int) $order->id);
            }
        } else {
            $gatewayLog->log(self::GATEWAY_ID, 'callback_failed', 'Оплата не прошла: заказ ' . $order->number . ' — ' . ($response->getMessage() ?? 'отклонено'), [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => (float) $payment->getAmount(),
                'message' => $response->getMessage(),
                'contact_email' => $order->contact_email,
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');
        }
    }

    /**
     * Обработка webhook REFUND — уведомление о завершении возврата.
     * data: { order: { id }, refundId?, amount?, refundStatus? }
     */
    private function handleRefundEvent(array $payloadClean, mixed $requestIp, ?string $signature, GatewayLoggerInterface $gatewayLog): void
    {
        $data = $payloadClean['data'] ?? [];
        $orderId = $data['order']['id'] ?? $data['orderId'] ?? null;
        $refundId = $data['refundId'] ?? $data['refund']['id'] ?? null;
        $refundStatus = strtoupper((string) ($data['refundStatus'] ?? $data['status']['value'] ?? $data['status'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);

        if (empty($orderId)) {
            Log::warning('ProcessRaiffeisenEcomCallbackJob REFUND: missing orderId');
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Webhook REFUND без orderId', [
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
            return;
        }

        $refundRecord = PaymentRefund::where('refund_id', (string) $refundId)->first();
        if ($refundRecord && in_array($refundStatus, ['COMPLETED', 'DONE', 'SUCCESS'], true)) {
            $refundRecord->update([
                'status' => PaymentRefund::STATUS_COMPLETED,
                'meta' => array_merge($refundRecord->meta ?? [], [
                    'webhook_data' => $data,
                    'refund_status' => $refundStatus,
                ]),
            ]);

            $payment = $refundRecord->payment;
            $totalRefunded = (float) PaymentRefund::where('payment_id', $payment->id)
                ->where('status', PaymentRefund::STATUS_COMPLETED)
                ->sum('amount');

            $payment->status_message = ($payment->status_message ? $payment->status_message . ' ' : '') . 'Возврат ' . number_format($amount, 2) . ' ₽ (webhook)';
            if ($totalRefunded >= (float) $payment->amount_paid) {
                $payment->status = \Vanilo\Payment\Models\PaymentStatusProxy::REFUNDED();
            } else {
                $payment->status = \Vanilo\Payment\Models\PaymentStatusProxy::PARTIALLY_REFUNDED();
            }
            $payment->save();

            $gatewayLog->log(self::GATEWAY_ID, 'refund_webhook_success', 'Подтверждён возврат: ' . $refundId . ', сумма ' . number_format($amount, 2) . ' ₽', [
                'order_number' => $orderId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'payment_id' => $payment->id,
                'ip' => $requestIp,
            ], $payment, 'payment', 'info');
        } elseif ($refundRecord) {
            $gatewayLog->log(self::GATEWAY_ID, 'refund_webhook_received', 'Получен webhook REFUND: ' . $refundStatus, [
                'order_number' => $orderId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'refund_status' => $refundStatus,
                'ip' => $requestIp,
            ], $refundRecord->payment, 'payment', 'info');
        } else {
            $gatewayLog->log(self::GATEWAY_ID, 'refund_webhook_unknown', 'Webhook REFUND для неизвестного возврата: ' . $refundId, [
                'order_number' => $orderId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'ip' => $requestIp,
            ], null, 'payment', 'warning');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessRaiffeisenEcomCallbackJob failed: ' . $e->getMessage(), [
            'payload' => $this->payload,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
