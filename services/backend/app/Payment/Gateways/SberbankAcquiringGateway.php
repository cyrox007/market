<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use App\Services\Payment\SberbankAcquiringClient;
use Illuminate\Http\Request;
use Vanilo\Contracts\Address;
use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentGateway;
use Vanilo\Payment\Contracts\PaymentRequest;
use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Contracts\TransactionHandler;
use Vanilo\Payment\Models\PaymentStatusProxy;

class SberbankAcquiringGateway implements PaymentGateway
{
    public static function getName(): string
    {
        return __('Сбербанк (оплата картой онлайн)');
    }

    public static function svgIcon(): string
    {
        return '<svg viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M64 32C28.7 32 0 60.7 0 96v320c0 35.3 28.7 64 64 64h448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm64 256h64v64H128v-64zm0-96h64v64H128v-64zm0-96h64v64H128V96zm96 192h64v64H224v-64zm0-96h64v64H224v-64zm0-96h64v64H224V96zm96 192h64v64H320v-64zm0-96h64v64H320v-64zm0-96h64v64H320V96zm96 192h64v64H416v-64zm0-96h64v64H416v-64zm0-96h64v64H416V96z"/></svg>';
    }

    public function __construct(
        private readonly SberbankAcquiringClient $client,
    ) {
    }

    public function createPaymentRequest(
        Payment $payment,
        ?Address $shippingAddress = null,
        array $options = []
    ): PaymentRequest {
        return new SberbankPaymentRequest($payment);
    }

    /**
     * Callback/webhook обработка будет в отдельном Job, здесь только мэппинг статуса.
     */
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

        $operation = strtoupper((string) ($payload['operation'] ?? ''));
        $status = strtoupper((string) ($payload['status'] ?? $payload['state'] ?? $payload['paymentStatus'] ?? ''));
        $rawStatus = $payload['status'] ?? null;
        $amount = (float) ($payload['amount'] ?? 0);
        $transactionId = $payload['mdOrder']
            ?? $payload['orderId']
            ?? $payload['order_id']
            ?? $payload['transactionId']
            ?? $payload['id']
            ?? null;
        $message = $payload['message'] ?? $payload['errorMessage'] ?? null;

        // Callback Сбера: operation=deposited|approved, status=1 — успех (см. ecom API)
        $wasSuccessful = in_array($operation, ['DEPOSITED', 'APPROVED'], true)
            || in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'CONFIRMED', 'DEPOSITED', 'APPROVED'], true)
            || $rawStatus === 1
            || $rawStatus === '1';
        $isFailed = in_array($operation, ['DECLINED', 'REVERSED', 'REFUNDED'], true)
            || in_array($status, ['DECLINED', 'FAILED', 'CANCELLED', 'CANCELED', 'EXPIRED', 'REJECTED'], true)
            || $rawStatus === 0
            || $rawStatus === '0';

        if ($wasSuccessful) {
            $vaniloStatus = PaymentStatusProxy::PAID();
        } elseif ($isFailed) {
            $vaniloStatus = in_array($status, ['CANCELLED', 'CANCELED', 'EXPIRED'], true)
                ? PaymentStatusProxy::CANCELLED()
                : PaymentStatusProxy::DECLINED();
        } else {
            $vaniloStatus = PaymentStatusProxy::PENDING();
        }

        return new SberbankPaymentResponse(
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

    /**
     * Возвращает конфиг для фронта: ссылку на оплату в Сбере.
     */
    public function getClientConfig(AppPaymentMethod $method, Order $order, bool $refreshRegistration = false): ?array
    {
        $paymentModelClass = \Vanilo\Payment\Models\PaymentProxy::modelClass();
        $payment = $paymentModelClass::where('payable_type', 'order')
            ->where('payable_id', $order->id)
            ->orderByDesc('id')
            ->first();

        $cachedFormUrl = is_array($payment?->data)
            ? trim((string) ($payment->data['sberbank_form_url'] ?? ''))
            : '';

        if (
            !$refreshRegistration
            && $payment
            && $cachedFormUrl !== ''
            && $payment->status->isPending()
        ) {
            return [
                'useEcomApi' => true,
                'payformUrl' => $cachedFormUrl,
            ];
        }

        $frontendUrl = (string) (config('app.frontend_url') ?? env('APP_FRONTEND_URL', env('FRONTEND_URL', '')));
        $frontendUrl = rtrim($frontendUrl, '/');

        $returnUrl = $frontendUrl . '/orders/' . $order->id . '?payment=success';
        $failUrl = $frontendUrl . '/orders/' . $order->id . '?payment=fail';

        $registerOrderNumber = (string) $order->number;
        if ($refreshRegistration && $payment) {
            $attempt = (int) ($payment->data['sberbank_register_attempt'] ?? 0) + 1;
            $registerOrderNumber = $order->number . '-R' . $payment->id . '-' . $attempt;
        }

        $result = $this->client->registerOrder(
            $order,
            (float) $order->total,
            $returnUrl,
            $failUrl,
            $registerOrderNumber,
        );

        if (!($result['ok'] ?? false)) {
            // Важно: если банк не вернул форму оплаты, фронту некуда редиректить.
            // Пусть OrderController вернёт 503 (payment_init_failed), чтобы не уводить пользователя на 404.
            return null;
        }

        $formUrl = (string) $result['form_url'];

        // Сохраняем remote_id и formUrl, чтобы повторный payment-config не регистрировал заказ заново.
        try {
            if ($payment) {
                if (!empty($result['remote_id'])) {
                    $payment->remote_id = (string) $result['remote_id'];
                }
                $data = is_array($payment->data) ? $payment->data : [];
                $data['sberbank_form_url'] = $formUrl;
                if ($refreshRegistration) {
                    $data['sberbank_register_attempt'] = (int) ($data['sberbank_register_attempt'] ?? 0) + 1;
                }
                $payment->data = $data;
                $payment->save();
            }
        } catch (\Throwable) {
            // Не ломаем UX оплаты, если не удалось сохранить данные платежа
        }

        return [
            'useEcomApi' => true,
            'payformUrl' => $formUrl,
        ];
    }
}

