<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Vanilo\Payment\Contracts\Payment;

/**
 * Создаёт RaiffeisenEcomClient с конфигурацией из метода оплаты.
 */
class RaiffeisenEcomClientFactory
{
    public function createForPayment(Payment $payment): RaiffeisenEcomClient
    {
        $method = $payment->getMethod();
        $config = $method->configuration() ?? [];
        $publicId = (string) ($config['public_id'] ?? config('payment.raiffeisen_ecom.public_id') ?? env('RAIFFEISEN_ECOM_PUBLIC_ID', ''));
        $secretKey = (string) ($config['secret_key'] ?? config('payment.raiffeisen_ecom.secret_key') ?? env('RAIFFEISEN_ECOM_SECRET_KEY', ''));
        $isTest = (bool) ($config['is_test'] ?? config('payment.raiffeisen_ecom.is_test', true));

        if ($publicId === '') {
            $publicId = (string) (config('payment.raiffeisen.public_id') ?? env('RAIFFEISEN_PUBLIC_ID', ''));
        }
        if ($secretKey === '') {
            $secretKey = (string) (config('payment.raiffeisen.secret_key') ?? env('RAIFFEISEN_SECRET_KEY', ''));
        }

        if ($publicId === '' || $secretKey === '') {
            throw new \RuntimeException('Raiffeisen e-commerce: не настроены public_id или secret_key');
        }

        return new RaiffeisenEcomClient($publicId, $secretKey, $isTest);
    }
}
