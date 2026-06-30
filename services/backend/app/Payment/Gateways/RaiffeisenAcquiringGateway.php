<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use Illuminate\Http\Request;
use Vanilo\Contracts\Address;
use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentGateway;
use Vanilo\Payment\Contracts\PaymentRequest;
use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Contracts\TransactionHandler;
use Vanilo\Payment\Models\PaymentStatusProxy;

class RaiffeisenAcquiringGateway implements PaymentGateway
{
    public static function getName(): string
    {
        return __('Райффайзен (оплата картой онлайн на сайте)');
    }

    public static function svgIcon(): string
    {
        return '<svg viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M64 32C28.7 32 0 60.7 0 96v320c0 35.3 28.7 64 64 64h448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm64 256h64v64H128v-64zm0-96h64v64H128v-64zm0-96h64v64H128V96zm96 192h64v64H224v-64zm0-96h64v64H224v-64zm0-96h64v64H224V96zm96 192h64v64H320v-64zm0-96h64v64H320v-64zm0-96h64v64H320V96zm96 192h64v64H416v-64zm0-96h64v64H416v-64zm0-96h64v64H416V96z"/></svg>';
    }

    public function createPaymentRequest(
        Payment $payment,
        ?Address $shippingAddress = null,
        array $options = []
    ): PaymentRequest {
        return new RaiffeisenPaymentRequest($payment);
    }

    private const SUCCESS_STATUSES = ['CONFIRMED', 'PAID', 'SUCCESS', 'COMPLETED', 'CAPTURED'];
    private const FAIL_STATUSES = ['DECLINED', 'CANCELLED', 'CANCELED', 'FAILED', 'REJECTED', 'EXPIRED'];

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

        $status = strtoupper((string) ($payload['status'] ?? $payload['state'] ?? $payload['paymentStatus'] ?? ''));
        $amount = (float) ($payload['amount'] ?? $payload['transactionAmount'] ?? 0);
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? $payload['paymentId'] ?? $payload['id'] ?? null;
        $message = $payload['message'] ?? $payload['errorMessage'] ?? null;

        $wasSuccessful = in_array($status, self::SUCCESS_STATUSES, true);
        $isFailed = in_array($status, self::FAIL_STATUSES, true);

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

    public function getClientConfig(AppPaymentMethod $method): array
    {
        $config = $method->configuration() ?? [];
        $fromConfig = config('payment.raiffeisen.public_id', '');
        if ((string) $fromConfig === '') {
            $fromConfig = env('RAIFFEISEN_PUBLIC_ID', '');
        }
        $fromMethod = $config['public_id'] ?? null;
        $publicId = ($fromMethod !== null && (string) $fromMethod !== '') ? (string) $fromMethod : (string) $fromConfig;

        $urlConfig = config('payment.raiffeisen.url', '');
        if ((string) $urlConfig === '') {
            $urlConfig = env('RAIFFEISEN_PAYMENT_URL', 'https://pay-test.raif.ru/pay');
        }
        $url = ($config['url'] ?? null) !== null && (string) ($config['url'] ?? '') !== ''
            ? (string) $config['url']
            : (string) $urlConfig;

        return [
            'publicId' => $publicId,
            'url' => $url,
            'useSdk' => true,
        ];
    }
}
