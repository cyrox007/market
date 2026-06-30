<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Vanilo\Payment\Contracts\PaymentRequest;

class RaiffeisenPaymentRequest implements PaymentRequest
{
    public function __construct(
        protected mixed $payment
    ) {
    }

    public function getHtmlSnippet(array $options = []): ?string
    {
        return null;
    }

    public function willRedirect(): bool
    {
        return true;
    }

    public function getRemoteId(): ?string
    {
        return null;
    }
}
