<?php

declare(strict_types=1);

namespace App\Payment\Gateways;

use Konekt\Enum\Enum;
use Vanilo\Payment\Contracts\PaymentStatus;
use Vanilo\Payment\Models\PaymentStatusProxy;
use Vanilo\Payment\Responses\NullStatus;

class RaiffeisenPaymentResponse implements \Vanilo\Payment\Contracts\PaymentResponse
{
    public function __construct(
        private readonly string $paymentId,
        private readonly bool $wasSuccessful,
        private readonly PaymentStatus $status,
        private readonly ?string $message,
        private readonly ?string $transactionId,
        private readonly float $transactionAmount
    ) {
    }

    public function wasSuccessful(): bool
    {
        return $this->wasSuccessful;
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
        return $this->transactionAmount > 0 ? $this->transactionAmount : null;
    }

    public function getTransactionAmount(): float
    {
        return $this->transactionAmount;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getNativeStatus(): Enum
    {
        return NullStatus::create();
    }
}
