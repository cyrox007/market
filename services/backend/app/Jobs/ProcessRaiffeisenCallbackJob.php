<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Jobs\Concerns\ChecksRaiffeisenCallbackIp;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentRefund;
use App\Payment\Gateways\AbstractRaiffeisenGateway;
use App\Services\Payment\RaiffeisenEcomClientFactory;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Vanilo\Payment\Models\PaymentProxy;
use Vanilo\Payment\Models\PaymentStatusProxy;
use Vanilo\Payment\Processing\PaymentResponseHandler;

/**
 * Единый обработчик callback Raif Pay для всех потоков (эквайринг и e-commerce).
 *
 * Продукт один, формат callback единый (см. pay.raif.ru/doc/ecom.html): event
 * "PAYMENT"/"REFUND", data:{ order:{id}, status:{value}, amount }, подпись в заголовке
 * X-Api-Signature-SHA256. Конкретный поток определяется gateway'ем платежа
 * (полиморфизм AbstractRaiffeisenGateway) — джобе не нужно знать его заранее.
 *
 * Защита (Р-1): IP-allowlist источника + обязательная проверка подписи (fail-closed).
 */
class ProcessRaiffeisenCallbackJob implements ShouldQueue
{
    use ChecksRaiffeisenCallbackIp;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /** Идентификатор для логов до того, как определён конкретный gateway. */
    private const LOG_ID = 'raiffeisen';

    /**
     * @param array<string, mixed> $payload Тело callback + служебные _request_ip/_signature.
     */
    public function __construct(
        private readonly array $payload
    ) {
        $this->queue = 'default';
    }

    public function handle(GatewayLoggerInterface $gatewayLog, RaiffeisenEcomClientFactory $clientFactory): void
    {
        $requestIp = $this->payload['_request_ip'] ?? null;
        $signature = $this->payload['_signature'] ?? null;
        $payloadClean = array_diff_key($this->payload, array_flip(['_request_ip', '_request_user_agent', '_signature']));

        // Р-1: проверка IP источника (мягкий режим — только лог; жёсткий — отклонение).
        if (!$this->passesCallbackIpCheck($requestIp, self::LOG_ID, $gatewayLog)) {
            return;
        }

        $event = strtoupper((string) ($payloadClean['event'] ?? 'PAYMENT'));
        if ($event === 'REFUND') {
            $this->handleRefundEvent($payloadClean, $requestIp, $gatewayLog);

            return;
        }
        if ($event !== 'PAYMENT') {
            $this->reject($gatewayLog, self::LOG_ID, 'Callback с неподдерживаемым event: ' . $event, [
                'event' => $event,
                'ip' => $requestIp,
            ]);

            return;
        }

        $context = $this->resolvePaymentContext($payloadClean, $requestIp, $gatewayLog);
        if ($context === null) {
            return;
        }
        [$order, $payment, $gateway] = $context;
        $logId = $gateway->gatewayLogId();

        // --- Fail-closed: подпись обязательна (иначе статус оплаты можно подделать, Р-1). ---
        if (!$signature) {
            $this->reject($gatewayLog, $logId, 'Callback отклонён: отсутствует подпись', [
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order);

            return;
        }

        try {
            $client = $clientFactory->createForPayment($payment);
        } catch (\Throwable $e) {
            $this->reject($gatewayLog, $logId, 'Callback отклонён: нет учётных данных для проверки подписи', [
                'order_number' => $order->number,
                'ip' => $requestIp,
                'error' => $e->getMessage(),
            ], $order);

            return;
        }

        if (!$client->verifyPaymentSignature($signature, $payloadClean)) {
            $this->reject($gatewayLog, $logId, 'Неверная подпись callback', [
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order);

            return;
        }

        $this->applyPaymentResult($gateway, $payment, $order, $payloadClean, $requestIp, $gatewayLog, $logId);
    }

    /**
     * Находит заказ, платёж и Raif-gateway по данным callback.
     *
     * @return array{0: Order, 1: \Vanilo\Payment\Contracts\Payment, 2: AbstractRaiffeisenGateway}|null
     */
    private function resolvePaymentContext(array $payloadClean, mixed $requestIp, GatewayLoggerInterface $gatewayLog): ?array
    {
        $data = $payloadClean['data'] ?? $payloadClean;
        $orderId = $data['order']['id'] ?? $data['orderId'] ?? $data['order_id'] ?? $data['id'] ?? null;
        if (empty($orderId)) {
            $this->reject($gatewayLog, self::LOG_ID, 'Callback без orderId', [
                'payload_keys' => array_keys($payloadClean),
                'ip' => $requestIp,
            ]);

            return null;
        }

        $order = Order::where('number', (string) $orderId)->first();
        if (!$order) {
            $this->reject($gatewayLog, self::LOG_ID, 'Заказ не найден: ' . $orderId, [
                'order_number' => $orderId,
                'ip' => $requestIp,
            ]);

            return null;
        }

        $payment = PaymentProxy::modelClass()::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();
        if (!$payment) {
            $this->reject($gatewayLog, self::LOG_ID, 'Платёж не найден для заказа ' . $order->number, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order);

            return null;
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof AbstractRaiffeisenGateway) {
            Log::warning('ProcessRaiffeisenCallbackJob: gateway платежа не относится к Raif Pay', ['payment_id' => $payment->id]);

            return null;
        }

        return [$order, $payment, $gateway];
    }

    /**
     * Проводит платёж по проверенному callback и меняет статус заказа.
     */
    private function applyPaymentResult(
        AbstractRaiffeisenGateway $gateway,
        mixed $payment,
        Order $order,
        array $payloadClean,
        mixed $requestIp,
        GatewayLoggerInterface $gatewayLog,
        string $logId
    ): void {
        $gatewayLog->log($logId, 'callback_received', 'Получен callback по заказу ' . $order->number, [
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
            if ($order instanceof \Vanilo\Contracts\Payable) {
                $order->setPayableRemoteId($response->getTransactionId());
            }
        }

        $handler->fireEvents();

        if (!$response->wasSuccessful()) {
            $gatewayLog->log($logId, 'callback_failed', 'Оплата не прошла: заказ ' . $order->number . ' — ' . ($response->getMessage() ?? 'отклонено'), [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'amount' => (float) $payment->getAmount(),
                'message' => $response->getMessage(),
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');

            return;
        }

        $gatewayLog->log($logId, 'callback_success', 'Оплата получена: заказ ' . $order->number . ', сумма ' . (float) $payment->getAmount() . ' ₽', [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'amount' => (float) $payment->getAmount(),
            'remote_id' => $response->getTransactionId(),
            'contact_email' => $order->contact_email,
            'ip' => $requestIp,
        ], $payment, 'payment', 'info');

        if (in_array($order->status, [OrderStatus::NEW->value, OrderStatus::AWAITING_PAYMENT->value], true)) {
            $order->changeStatus(OrderStatus::ACCEPTED, 'Оплата получена (Райффайзен)', null);
        } else {
            OrderOneCSyncDispatcher::dispatch((int) $order->id);
        }
    }

    /**
     * Webhook REFUND — уведомление о завершении возврата (обновление записи PaymentRefund).
     */
    private function handleRefundEvent(array $payloadClean, mixed $requestIp, GatewayLoggerInterface $gatewayLog): void
    {
        $data = $payloadClean['data'] ?? [];
        $orderId = $data['order']['id'] ?? $data['orderId'] ?? null;
        $refundId = $data['refundId'] ?? $data['refund']['id'] ?? null;
        $refundStatus = strtoupper((string) ($data['refundStatus'] ?? $data['status']['value'] ?? $data['status'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);

        $refundRecord = $refundId ? PaymentRefund::where('refund_id', (string) $refundId)->first() : null;

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
            $payment->status = $totalRefunded >= (float) $payment->amount_paid
                ? PaymentStatusProxy::REFUNDED()
                : PaymentStatusProxy::PARTIALLY_REFUNDED();
            $payment->save();

            $gatewayLog->log(self::LOG_ID, 'refund_webhook_success', 'Подтверждён возврат: ' . $refundId . ', сумма ' . number_format($amount, 2) . ' ₽', [
                'order_number' => $orderId,
                'refund_id' => $refundId,
                'amount' => $amount,
                'payment_id' => $payment->id,
                'ip' => $requestIp,
            ], $payment, 'payment', 'info');

            return;
        }

        $gatewayLog->log(self::LOG_ID, 'refund_webhook_received', 'Webhook REFUND: ' . ($refundStatus ?: 'неизвестный статус'), [
            'order_number' => $orderId,
            'refund_id' => $refundId,
            'amount' => $amount,
            'refund_status' => $refundStatus,
            'ip' => $requestIp,
        ], $refundRecord?->payment, 'payment', 'warning');
    }

    /**
     * Единая точка логирования отказа обработки callback.
     */
    private function reject(GatewayLoggerInterface $gatewayLog, string $logId, string $message, array $meta, mixed $subject = null): void
    {
        Log::warning('ProcessRaiffeisenCallbackJob: ' . $message, $meta);
        $gatewayLog->log($logId, 'callback_rejected', $message, $meta, $subject, 'payment', 'warning');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessRaiffeisenCallbackJob failed: ' . $e->getMessage(), [
            'payload' => $this->payload,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
