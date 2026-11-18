<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Konekt\Enum\Enum;
use Vanilo\Payment\Contracts\PaymentResponse;
use Vanilo\Payment\Responses\NullStatus;
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

    public function getAmountPaid(): ?float
    {
        return $this->wasSuccessful && $this->transactionAmount > 0
            ? $this->transactionAmount : null;
    }

    public function getTransactionAmount(): float
    {
        return $this->wasSuccessful ? $this->transactionAmount : 0.0;
    }

    public function getNativeStatus(): Enum
    {
        return NullStatus::create();
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }
}

