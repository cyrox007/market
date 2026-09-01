<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Illuminate\Http\Request;
use Vanilo\Payment\Contracts\PaymentGateway;
use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Contracts\TransactionHandler;
use Vanilo\Payment\Models\PaymentStatusProxy;

/**
 * Общая база gateway'ев Raif Pay.
 *
 * Эквайринг и e-commerce — один продукт с единым форматом callback
 * (event "PAYMENT", data: { order:{id}, status:{value}, amount }; подпись
 * X-Api-Signature-SHA256), см. https://pay.raif.ru/doc/ecom.html. Приём и разбор
 * уведомления одинаковы для всех потоков, поэтому вынесены сюда. Подклассы
 * различаются только инициацией оплаты (createPaymentRequest / getClientConfig).
 */
abstract class AbstractRaiffeisenGateway implements PaymentGateway
{
    protected const SUCCESS_STATUSES = ['CONFIRMED', 'PAID', 'SUCCESS', 'COMPLETED', 'CAPTURED'];

    protected const FAIL_STATUSES = ['DECLINED', 'CANCELLED', 'CANCELED', 'FAILED', 'REJECTED', 'EXPIRED'];

    /** Идентификатор потока для логов gateway (raiffeisen_ecom | raiffeisen_acquiring). */
    abstract public function gatewayLogId(): string;

    public function processPaymentResponse(Request $request, array $options = []): PaymentResponse
    {
        $payment = $options['payment'] ?? null;
        $paymentId = $payment ? $payment->getPaymentId() : '';

        $payload = $request->all();
        if (empty($payload)) {
            $raw = $request->getContent();
            if (is_string($raw)) {
                $payload = json_decode($raw, true) ?? [];
            }
        }

        // Единый формат Raif Pay (вложенный); fallback на плоскую структуру — на случай legacy.
        $data = $payload['data'] ?? $payload;
        $status = strtoupper((string) ($data['status']['value'] ?? $data['status'] ?? $data['state'] ?? $data['paymentStatus'] ?? ''));
        $amount = (float) ($data['amount'] ?? $data['transactionAmount'] ?? 0);
        $transactionId = $data['id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? $data['paymentId'] ?? null;
        $message = $data['message'] ?? $data['errorMessage'] ?? null;

        $wasSuccessful = in_array($status, static::SUCCESS_STATUSES, true);
        $isFailed = in_array($status, static::FAIL_STATUSES, true);

        if ($wasSuccessful) {
            $vaniloStatus = PaymentStatusProxy::PAID();
        } elseif ($isFailed) {
            $vaniloStatus = in_array($status, ['CANCELLED', 'CANCELED', 'EXPIRED'], true)
                ? PaymentStatusProxy::CANCELLED()
                : PaymentStatusProxy::DECLINED();
        } else {
            $vaniloStatus = PaymentStatusProxy::PENDING();
        }

        return new RaiffeisenPaymentResponse(
            paymentId: $paymentId,
            wasSuccessful: $wasSuccessful,
            status: $vaniloStatus,
            message: $message,
            transactionId: $transactionId ? (string) $transactionId : null,
            transactionAmount: $amount > 0 ? $amount : ($payment ? (float) $payment->getAmount() : 0.0)
        );
    }

    public function transactionHandler(): ?TransactionHandler
    {
        return null;
    }

    public function isOffline(): bool
    {
        return false;
    }
}
