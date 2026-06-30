<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use App\Services\Payment\RaiffeisenEcomClient;
use Illuminate\Http\Request;
use Vanilo\Contracts\Address;
use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentGateway;
use Vanilo\Payment\Contracts\PaymentRequest;
use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Contracts\TransactionHandler;
use Vanilo\Payment\Models\PaymentStatusProxy;

/**
 * Шлюз Raiffeisen e-commerce API (pay.raif.ru).
 * Доступен для всех регионов. Тестовый контур: pay-test.raif.ru.
 */
class RaiffeisenEcomGateway implements PaymentGateway
{
    public static function getName(): string
    {
        return __('Райффайзен e-commerce (оплата картой, все регионы)');
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
        return new RaiffeisenEcomPaymentRequest($payment);
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

        // Webhook format: event "PAYMENT", data: { order: { id }, status: { value }, amount }
        $data = $payload['data'] ?? $payload;
        $orderId = $data['order']['id'] ?? $data['orderId'] ?? $data['order_id'] ?? null;
        $status = strtoupper((string) ($data['status']['value'] ?? $data['status'] ?? $data['state'] ?? ''));
        $amount = (float) ($data['amount'] ?? $data['transactionAmount'] ?? 0);
        $transactionId = $data['id'] ?? $data['transactionId'] ?? $data['paymentId'] ?? null;
        $message = $data['message'] ?? $data['errorMessage'] ?? null;

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

    /**
     * Возвращает payformUrl после вызова API создания заказа.
     * Для e-commerce API ссылка формируется на стороне банка.
     */
    public function getClientConfig(AppPaymentMethod $method, ?\App\Models\Order\Order $order = null): ?array
    {
        $config = $method->configuration() ?? [];
        $publicId = (string) ($config['public_id'] ?? config('payment.raiffeisen_ecom.public_id') ?? env('RAIFFEISEN_ECOM_PUBLIC_ID', ''));
        $secretKey = (string) ($config['secret_key'] ?? config('payment.raiffeisen_ecom.secret_key') ?? env('RAIFFEISEN_ECOM_SECRET_KEY', ''));
        $isTest = (bool) ($config['is_test'] ?? config('payment.raiffeisen_ecom.is_test', true));

        // Fallback на ключи acquiring, если ecom не заданы (один мерчант — одни учётные данные)
        if ($publicId === '') {
            $publicId = (string) (config('payment.raiffeisen.public_id') ?? env('RAIFFEISEN_PUBLIC_ID', ''));
        }
        if ($secretKey === '') {
            $secretKey = (string) (config('payment.raiffeisen.secret_key') ?? env('RAIFFEISEN_SECRET_KEY', ''));
        }

        if ($publicId === '' || $secretKey === '') {
            return null;
        }

        if (!$order) {
            return [
                'publicId' => $publicId,
                'url' => $isTest ? 'https://pay-test.raif.ru/pay' : 'https://pay.raif.ru/pay',
                'useEcomApi' => true,
            ];
        }

        $baseUrl = rtrim(env('APP_FRONTEND_URL', env('FRONTEND_URL', request()->getSchemeAndHttpHost())), '/');
        $successUrl = $baseUrl . '/orders/' . $order->id . '?payment=success';
        $failUrl = $baseUrl . '/orders/' . $order->id . '?payment=fail';

        try {
            $client = new RaiffeisenEcomClient($publicId, $secretKey, $isTest);
            $result = $client->createOrder(
                $order->number,
                (float) $order->total,
                'Заказ ' . $order->number,
                $successUrl,
                $failUrl
            );

            $payformUrl = $result['payformUrl'] ?? null;
            if ($payformUrl) {
                return [
                    'payformUrl' => $payformUrl,
                    'useEcomApi' => true,
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('RaiffeisenEcom API failed, fallback to GET /pay: ' . $e->getMessage());
        }

        // Fallback: GET /pay с query-параметрами (работает без secretKey, когда POST API заблокирован)
        $payUrl = $isTest ? 'https://pay-test.raif.ru/pay' : 'https://pay.raif.ru/pay';
        return [
            'publicId' => $publicId,
            'url' => $payUrl,
            'useSdk' => true,
        ];
    }
}
