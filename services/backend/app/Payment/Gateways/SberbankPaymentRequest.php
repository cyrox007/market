<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Vanilo\Payment\Contracts\Payment;
use Vanilo\Payment\Contracts\PaymentRequest;

class SberbankPaymentRequest implements PaymentRequest
{
    public function __construct(
        public readonly Payment $payment,
    ) {
    }

    public function getHtmlSnippet(): ?string
    {
        return null;
    }

    public function willRedirect(): bool
    {
        return true;
    }

    public function getTargetUrl(): ?string
    {
        return null;
    }
}

