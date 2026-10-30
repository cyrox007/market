<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Support\Integration\OrderOneCSyncDispatcher;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Services\Payment\SberbankAcquiringClient;
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
 * Обработка callback от Сбербанка (POST: mdOrder, orderNumber, operation, status).
 */
class ProcessSberbankCallbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    private const GATEWAY_ID = 'sberbank_acquiring';

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
        $payloadClean = array_diff_key($this->payload, array_flip(['_request_ip', '_request_user_agent']));

        $mdOrder = $payloadClean['mdOrder'] ?? $payloadClean['orderId'] ?? null;
        $orderNumber = $payloadClean['orderNumber'] ?? $payloadClean['order_number'] ?? null;

        if (empty($mdOrder) && empty($orderNumber)) {
            Log::warning('ProcessSberbankCallbackJob: missing mdOrder and orderNumber', [
                'payload_keys' => array_keys($payloadClean),
            ]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Callback без mdOrder/orderNumber', [
                'payload_keys' => array_keys($payloadClean),
                'ip' => $requestIp,
            ], null, 'payment', 'warning');

            return;
        }

        $order = $this->resolveOrder($mdOrder, $orderNumber);
        if (!$order) {
            Log::warning('ProcessSberbankCallbackJob: order not found', [
                'mdOrder' => $mdOrder,
                'orderNumber' => $orderNumber,
            ]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Заказ не найден по callback', [
                'md_order' => $mdOrder,
                'order_number' => $orderNumber,
                'ip' => $requestIp,
            ], null, 'payment', 'warning');

            return;
        }

        $payment = PaymentProxy::modelClass()::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            Log::warning('ProcessSberbankCallbackJob: payment not found', ['order_id' => $order->id]);
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Платёж не найден для заказа ' . $order->number, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'ip' => $requestIp,
            ], $order, 'payment', 'warning');

            return;
        }

        // Callback orderId must belong to the payment being updated: it is
        // never sufficient to resolve a payable solely from an order number.
        if ($mdOrder && $payment->remote_id && ! hash_equals((string) $payment->remote_id, (string) $mdOrder)) {
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Идентификатор банка не совпадает с платёжной записью',
                ['order_id' => $order->id, 'payment_id' => $payment->id], $payment, 'payment', 'warning');
            return;
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof SberbankAcquiringGateway) {
            Log::warning('ProcessSberbankCallbackJob: invalid gateway for payment', ['payment_id' => $payment->id]);

            return;
        }

        $gatewayLog->log(self::GATEWAY_ID, 'callback_received', 'Получен callback по заказу ' . $order->number, [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'md_order' => $mdOrder,
            'operation' => $payloadClean['operation'] ?? null,
            'status' => $payloadClean['status'] ?? null,
            'amount' => (float) $payment->getAmount(),
            'contact_email' => $order->contact_email,
            'ip' => $requestIp,
        ], $payment, 'payment', 'info');

        // --- Р-1: обратная сверка статуса у банка. Callback — лишь триггер;
        // факт оплаты определяется прямым запросом getOrderStatusExtended, а НЕ полями callback.
        $bank = app(SberbankAcquiringClient::class)->getOrderStatusExtended(
            (string) ($mdOrder ?? ''),
            (string) ($orderNumber ?? $order->number)
        );

        if ($bank === []) {
            // Банк не ответил (недоступен или не заданы креды) — оплату НЕ подтверждаем (fail-safe).
            $gatewayLog->log(self::GATEWAY_ID, 'callback_unverified', 'Статус не подтверждён банком (getOrderStatusExtended не ответил) — оплата не проведена', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'md_order' => $mdOrder,
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');

            return;
        }

        $errorCode = (int) ($bank['errorCode'] ?? 0);
        $bankOrderStatus = isset($bank['orderStatus']) ? (int) $bank['orderStatus'] : -1;
        $bankAmount = isset($bank['amount']) && is_numeric($bank['amount'])
            ? (int) $bank['amount'] : 0; // Сбер отвечает в КОПЕЙКАХ
        $expectedAmount = (int) round(((float) $payment->getAmount()) * 100);

        // Bank status 2 with EXACT amount is the only authoritative proof of
        // payment. A missing amount must not confirm payment either.
        $paid = ($errorCode === 0 && $bankOrderStatus === 2
            && $bankAmount > 0 && $bankAmount === $expectedAmount);

        if ($errorCode === 0 && $bankOrderStatus === 2 && ! $paid) {
            $gatewayLog->log(self::GATEWAY_ID, 'callback_rejected', 'Сумма банка не совпадает с заказом: ' . $bankAmount . ' коп vs ' . $expectedAmount . ' коп — оплата не проведена', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'bank_amount' => $bankAmount,
                'expected_amount' => $expectedAmount,
                'bank_order_status' => $bankOrderStatus,
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');

            return;
        }

        // Обновление платежа/заказа — по ДОВЕРЕННОМУ статусу банка, а не по телу callback.
        // 0/1/5 = registered/authorized/in authentication, not a bank refusal.
        // 3/6 = reversed/declined, 4 = refunded and handled by refund flow.
        if ($errorCode !== 0 || $bankOrderStatus === 4) {
            $gatewayLog->log(self::GATEWAY_ID, 'callback_unverified',
                'Неподтверждённый или возвращённый статус банка — платёж не меняем',
                ['order_id' => $order->id, 'bank_order_status' => $bankOrderStatus, 'error_code' => $errorCode],
                $payment, 'payment', 'warning');
            return;
        }
        $failed = in_array($bankOrderStatus, [3, 6], true);
        $trustedPayload = [
            'operation' => $paid ? 'deposited' : ($failed ? 'declined' : 'pending'),
            'status' => $paid ? 1 : ($failed ? 0 : 'PENDING'),
            'amount' => $paid ? round($bankAmount / 100, 2) : 0.0,
            'mdOrder' => (string) ($mdOrder ?? ''),
            'orderNumber' => $order->number,
            'actionCode' => $bank['actionCode'] ?? null,
            'message' => $bank['actionCodeDescription'] ?? $bank['errorMessage'] ?? null,
        ];
        $request = Request::create('/', 'POST', [], [], [], [], json_encode($trustedPayload));
        $request->headers->set('Content-Type', 'application/json');

        \Illuminate\Support\Facades\DB::transaction(function () use ($gateway, $payment, $order, $request, $gatewayLog, $requestIp, $payloadClean): void {
        $order = Order::lockForUpdate()->findOrFail($order->id);
        $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
        if ($payment->getStatus()->equals(\Vanilo\Payment\Models\PaymentStatusProxy::PAID())) { return; }
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
        app(\App\Services\Order\OrderRefundTasks::class)->ensure($order->fresh());

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
                    'Оплата получена (Сбербанк)',
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
                'operation' => $payloadClean['operation'] ?? null,
                'contact_email' => $order->contact_email,
                'ip' => $requestIp,
            ], $payment, 'payment', 'warning');
        }
        });
    }

    private function resolveOrder(mixed $mdOrder, mixed $orderNumber): ?Order
    {
        $paymentModel = PaymentProxy::modelClass();

        if (!empty($mdOrder)) {
            $payment = $paymentModel::query()
                ->where('remote_id', (string) $mdOrder)
                ->where('payable_type', 'order')
                ->orderByDesc('id')
                ->first();
            if ($payment && $payment->payable_id) {
                $order = Order::find($payment->payable_id);
                if ($order) {
                    return $order;
                }
            }
        }

        if (!empty($orderNumber)) {
            $orderNumber = (string) $orderNumber;
            $order = Order::where('number', $orderNumber)->first();
            if ($order) {
                return $order;
            }

            // Повторная регистрация: ORDER-1-R12-2 → ORDER-1
            if (preg_match('/^(.+)-R\d+-\d+$/', $orderNumber, $matches) === 1) {
                return Order::where('number', $matches[1])->first();
            }
        }

        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessSberbankCallbackJob failed: ' . $e->getMessage(), [
            'payload' => $this->payload,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
