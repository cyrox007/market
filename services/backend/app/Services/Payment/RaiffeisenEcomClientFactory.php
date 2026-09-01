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
        $config = $payment->getMethod()->configuration() ?? [];
        $isTest = (bool) ($config['is_test'] ?? config('payment.raiffeisen_ecom.is_test', true));

        // Креды берём из метода оплаты, затем из ecom-, затем acquiring-конфига
        // (один мерчант — одни учётные данные). Только config(): env() не читаем в рантайме
        // (после config:cache env() вернёт null).
        $publicId = $this->firstNonEmpty(
            $config['public_id'] ?? null,
            config('payment.raiffeisen_ecom.public_id'),
            config('payment.raiffeisen.public_id'),
        );
        $secretKey = $this->firstNonEmpty(
            $config['secret_key'] ?? null,
            config('payment.raiffeisen_ecom.secret_key'),
            config('payment.raiffeisen.secret_key'),
        );

        if ($publicId === '' || $secretKey === '') {
            throw new \RuntimeException('Raiffeisen e-commerce: не настроены public_id или secret_key');
        }

        return new RaiffeisenEcomClient($publicId, $secretKey, $isTest);
    }

    private function firstNonEmpty(?string ...$values): string
    {
        foreach ($values as $value) {
            if ((string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }
}
