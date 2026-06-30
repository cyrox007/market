<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Contracts\PaymentStatus;

class SberbankPaymentResponse implements PaymentResponse
{
    public function __construct(
        private readonly string $paymentId,
        private readonly bool $wasSuccessful,
        private readonly PaymentStatus $status,
        private readonly ?string $message = null,
        private readonly ?string $transactionId = null,
        private readonly float $transactionAmount = 0.0,
    ) {
    }

    public function wasSuccessful(): bool
    {
        return $this->wasSuccessful;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getAmountPaid(): float
    {
        return $this->transactionAmount;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}

