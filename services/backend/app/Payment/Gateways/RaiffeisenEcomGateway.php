<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use App\Services\Payment\RaiffeisenEcomClient;
use Vanilo\Contracts\Address;
use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentRequest;

/**
 * Шлюз Raiffeisen e-commerce API (pay.raif.ru).
 * Доступен для всех регионов. Тестовый контур: pay-test.raif.ru.
 * Приём callback наследуется от AbstractRaiffeisenGateway (единый механизм Raif Pay).
 */
class RaiffeisenEcomGateway extends AbstractRaiffeisenGateway
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

    /**
     * Возвращает payformUrl после вызова API создания заказа.
     * Для e-commerce API ссылка формируется на стороне банка.
     */
    public function gatewayLogId(): string
    {
        return 'raiffeisen_ecom';
    }

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
