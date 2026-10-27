<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Gateway\GatewayLoggerInterface;
use App\Models\Payment\PaymentRefund;
use App\Payment\Gateways\RaiffeisenEcomGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Vanilo\Payment\Models\PaymentProxy;
use Vanilo\Payment\Models\PaymentStatusProxy;

/**
 * Сервис возвратов через Raiffeisen e-commerce.
 * Оформляет возврат в API банка и обновляет статус платежа.
 */
class RaiffeisenEcomRefundService
{
    private const GATEWAY_ID = 'raiffeisen_ecom';

    public function __construct(
        private readonly RaiffeisenEcomClientFactory $clientFactory,
        private readonly GatewayLoggerInterface $gatewayLog
    ) {
    }

    /**
     * Оформить возврат по оплаченному платежу.
     *
     * @param int|object $payment Payment или payment id
     * @param float $amount Сумма возврата
     * @param string|null $comment Комментарий (для логов)
     * @param int|null $userId ID пользователя, оформившего возврат
     * @throws \RuntimeException если шлюз не RaiffeisenEcom или платёж не оплачен
     */
    public function refund($payment, float $amount, ?string $comment = null, ?int $userId = null): PaymentRefund
    {
        $payment = $this->resolvePayment($payment);
        if (! $payment) { throw new \RuntimeException('Платёж не найден'); }
        return \Illuminate\Support\Facades\Cache::lock('order-refund:'.$payment->payable_id, 180)->block(5,
            fn () => $this->refundLocked($payment, $amount, $comment, $userId));
    }

    private function refundLocked($payment, float $amount, ?string $comment, ?int $userId): PaymentRefund
    {
        $payment = $this->resolvePayment($payment);
        if (!$payment) {
            throw new \RuntimeException('Платёж не найден');
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof RaiffeisenEcomGateway) {
            throw new \RuntimeException('Возврат через Raiffeisen e-commerce доступен только для платежей card_ecom');
        }

        if (!$payment->getStatus()->equals(PaymentStatusProxy::PAID()) && !$payment->getStatus()->equals(PaymentStatusProxy::PARTIALLY_REFUNDED())) {
            throw new \RuntimeException('Возврат возможен только для оплаченных платежей');
        }

        $order = $payment->getPayable();
        if (!$order || !method_exists($order, 'getNumber')) {
            throw new \RuntimeException('Заказ не найден для платежа');
        }

        $orderNumber = (string) $order->getNumber();
        if (PaymentRefund::where('payment_id', $payment->id)->whereIn('status', ['pending', 'in_progress'])->exists()) {
            throw new \RuntimeException('Предыдущий возврат ещё проверяется банком. Сначала дождитесь подтверждения.');
        }
        $maxRefundable = $this->getRefundableAmount($payment);
        if ($amount <= 0 || $amount > $maxRefundable) {
            throw new \RuntimeException("Сумма возврата должна быть от 0.01 до {$maxRefundable}");
        }

        $refundId = 'RF-' . $orderNumber . '-' . substr(uniqid('', true), -8);

        $client = $this->clientFactory->createForPayment($payment);

        $refundRecord = PaymentRefund::create([
            'payment_id' => $payment->id,
            'refund_id' => $refundId,
            'amount' => $amount,
            'status' => PaymentRefund::STATUS_IN_PROGRESS,
            'gateway' => self::GATEWAY_ID,
            'comment' => $comment,
            'created_by' => $userId,
        ]);

        try {
            $apiResponse = $client->createRefund($orderNumber, $refundId, $amount);

            $confirmed = strtoupper((string) ($apiResponse['refundStatus'] ?? $apiResponse['status'] ?? '')) === 'COMPLETED';
            $refundRecord->update([
                'status' => $confirmed ? PaymentRefund::STATUS_COMPLETED : PaymentRefund::STATUS_IN_PROGRESS,
                'meta' => array_merge($refundRecord->meta ?? [], [
                    'api_response' => $apiResponse,
                    'refund_status' => $apiResponse['refundStatus'] ?? $apiResponse['status'] ?? 'PENDING',
                ]),
            ]);

            $totalRefunded = (float) PaymentRefund::where('payment_id', $payment->id)
                ->where('status', PaymentRefund::STATUS_COMPLETED)
                ->sum('amount');

            $payment->status_message = ($comment ? $comment . '. ' : '') . 'Возврат ' . number_format($amount, 2) . ' ₽ через Raif e-commerce';
            if ($totalRefunded >= (float) $payment->amount_paid) {
                $payment->status = PaymentStatusProxy::REFUNDED();
            } elseif ($totalRefunded > 0) {
                $payment->status = PaymentStatusProxy::PARTIALLY_REFUNDED();
            }
            $payment->save();
            if ($order instanceof \App\Models\Order\Order) { app(\App\Services\Order\OrderRefundTasks::class)->ensure($order); }

            $this->gatewayLog->log(self::GATEWAY_ID, 'refund_success', 'Возврат оформлен: заказ ' . $orderNumber . ', сумма ' . number_format($amount, 2) . ' ₽', [
                'order_id' => $order->getKey(),
                'order_number' => $orderNumber,
                'payment_id' => $payment->id,
                'refund_id' => $refundId,
                'amount' => $amount,
                'user_id' => $userId,
            ], $payment, 'payment', 'info');

            return $refundRecord;
        } catch (\Throwable $e) {
            $refundRecord->update([
                'status' => PaymentRefund::STATUS_IN_PROGRESS,
                'meta' => array_merge($refundRecord->meta ?? [], [
                    'error' => $e->getMessage(),
                ]),
            ]);

            $this->gatewayLog->log(self::GATEWAY_ID, 'refund_failed', 'Ошибка возврата: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'refund_id' => $refundId,
                'amount' => $amount,
                'user_id' => $userId,
            ], $payment, 'payment', 'error');

            throw $e;
        }
    }

    /**
     * Отменить заказ в API банка (для неоплаченных платежей Raiffeisen e-commerce).
     * Вызывает DELETE /orders/{orderId} в Raif API.
     *
     * @throws \RuntimeException если шлюз не RaiffeisenEcom или платёж уже оплачен
     */
    public function cancelInBank($payment, ?int $userId = null): void
    {
        $payment = $this->resolvePayment($payment);
        if (!$payment) {
            throw new \RuntimeException('Платёж не найден');
        }

        $gateway = $payment->getMethod()->getGateway();
        if (!$gateway instanceof RaiffeisenEcomGateway) {
            throw new \RuntimeException('Отмена в банке доступна только для платежей Raiffeisen e-commerce');
        }

        if ($payment->getStatus()->equals(PaymentStatusProxy::PAID())) {
            throw new \RuntimeException('Нельзя отменить оплаченный платёж. Используйте возврат.');
        }

        $order = $payment->getPayable();
        if (!$order || !method_exists($order, 'getNumber')) {
            throw new \RuntimeException('Заказ не найден для платежа');
        }

        $orderNumber = (string) $order->getNumber();
        $client = $this->clientFactory->createForPayment($payment);

        $client->deleteOrder($orderNumber);

        $payment->status = PaymentStatusProxy::CANCELLED();
        $payment->status_message = 'Отменён в API банка администратором';
        $payment->save();

        $this->gatewayLog->log(self::GATEWAY_ID, 'order_cancelled_in_bank', 'Заказ отменён в Raif API: ' . $orderNumber, [
            'order_id' => $order->getKey(),
            'order_number' => $orderNumber,
            'payment_id' => $payment->id,
            'user_id' => $userId,
        ], $payment, 'payment', 'info');
    }

    /**
     * Проверить, можно ли отменить платёж в API банка.
     */
    public function canCancelInBank($payment): bool
    {
        $payment = $this->resolvePayment($payment);
        if (!$payment) {
            return false;
        }

        if (!$payment->getMethod()->getGateway() instanceof RaiffeisenEcomGateway) {
            return false;
        }

        return !$payment->getStatus()->equals(PaymentStatusProxy::PAID())
            && !$payment->getStatus()->equals(PaymentStatusProxy::CANCELLED())
            && !$payment->getStatus()->equals(PaymentStatusProxy::REFUNDED());
    }

    /**
     * Проверить, доступен ли возврат для данного платежа.
     */
    public function canRefund($payment): bool
    {
        $payment = $this->resolvePayment($payment);
        if (!$payment) {
            return false;
        }

        if (!$payment->getMethod()->getGateway() instanceof RaiffeisenEcomGateway) {
            return false;
        }

        if (!$payment->getStatus()->equals(PaymentStatusProxy::PAID())
            && !$payment->getStatus()->equals(PaymentStatusProxy::PARTIALLY_REFUNDED())) {
            return false;
        }

        if (PaymentRefund::where('payment_id', $payment->id)->whereIn('status', ['pending', 'in_progress'])->exists()) { return false; }
        $totalRefunded = (float) PaymentRefund::where('payment_id', $payment->id)
            ->where('status', PaymentRefund::STATUS_COMPLETED)
            ->sum('amount');

        return $totalRefunded < (float) $payment->amount_paid;
    }

    /**
     * Сумма, доступная для возврата.
     */
    public function getRefundableAmount($payment): float
    {
        $payment = $this->resolvePayment($payment);
        if (!$payment) {
            return 0.0;
        }

        $totalRefunded = (float) PaymentRefund::where('payment_id', $payment->id)
            ->where('status', PaymentRefund::STATUS_COMPLETED)
            ->sum('amount');

        return max(0, (float) $payment->amount_paid - $totalRefunded);
    }

    /**
     * Привести аргумент к одной модели Payment (не Collection).
     */
    private function resolvePayment(mixed $payment): ?Model
    {
        if ($payment instanceof Collection) {
            $payment = $payment->first();
        }
        if ($payment instanceof Model) {
            return $payment->exists ? PaymentProxy::modelClass()::find($payment->getKey()) : null;
        }
        if (is_numeric($payment) || is_string($payment)) {
            return PaymentProxy::modelClass()::find($payment);
        }

        return null;
    }
}
