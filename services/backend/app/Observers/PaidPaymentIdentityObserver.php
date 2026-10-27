<?php
namespace App\Observers;

class PaidPaymentIdentityObserver
{
    public function updating(\Illuminate\Database\Eloquent\Model $payment): void
    {
        if ((float) $payment->getRawOriginal('amount_paid') <= 0
            && ! in_array((string) $payment->getRawOriginal('status'), ['paid', 'partially_refunded', 'refunded'], true)) { return; }
        if ($payment->isDirty(['payable_type', 'payable_id', 'payment_method_id', 'currency', 'amount'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Оплаченный платёж нельзя перенести на другой заказ или способ оплаты. Возврат выполняется только через исходный платёж.']);
        }
    }
}
