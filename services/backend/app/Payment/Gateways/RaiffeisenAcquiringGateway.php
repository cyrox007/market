<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use App\Models\Payment\PaymentMethod as AppPaymentMethod;
use Vanilo\Contracts\Address;
use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentRequest;

/**
 * Райффайзен — оплата картой через платёжную форму (client-side redirect на pay.raif.ru/pay).
 * Приём callback наследуется от AbstractRaiffeisenGateway (единый механизм Raif Pay).
 */
class RaiffeisenAcquiringGateway extends AbstractRaiffeisenGateway
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

    public function gatewayLogId(): string
    {
        return 'raiffeisen_acquiring';
    }

    public function getClientConfig(AppPaymentMethod $method): array
    {
        $config = $method->configuration() ?? [];

        $publicId = (string) ($config['public_id'] ?? config('payment.raiffeisen.public_id', ''));
        $url = (string) ($config['url'] ?? config('payment.raiffeisen.url', 'https://pay-test.raif.ru/pay'));

        return [
            'publicId' => $publicId,
            'url' => $url,
            'useSdk' => true,
        ];
    }
}
