<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualOrderPayment
{
    public function allowed(Order $order): bool
    {
        return PaymentMethod::where('code', $order->payment_method)->where('gateway', 'manual')->exists();
    }

    public function record(int $orderId, ?int $paymentId, bool $paid): Model
    {
        return DB::transaction(function () use ($orderId, $paymentId, $paid): Model {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if (! $this->allowed($order) || in_array($order->status, ['cancelled', 'delivered'], true)) {
                throw ValidationException::withMessages(['payment' => 'Банковскую оплату подтверждает только банк. Ручное изменение недоступно.']);
            }
            $method = PaymentMethod::where('code', $order->payment_method)->firstOrFail();
            $payment = $paymentId ? $order->payments()->lockForUpdate()->findOrFail($paymentId) : null;
            if ($payment && ((int) $payment->payment_method_id !== (int) $method->id
                || (float) $payment->amount_paid > 0 || in_array((string) $payment->getRawOriginal('status'), ['paid', 'partially_refunded', 'refunded', 'cancelled'], true))) {
                throw ValidationException::withMessages(['payment' => 'Платёж уже обработан. Обновите заказ; для возврата используется отдельная операция.']);
            }
            if (! $payment && ! $paid) {
                throw ValidationException::withMessages(['payment' => 'Нет платежа для отмены.']);
            }
            if (! $payment) {
                if ($order->payments()->where('amount_paid', '>', 0)->exists()) {
                    throw ValidationException::withMessages(['payment' => 'У заказа уже есть оплата. Обновите страницу.']);
                }
                $payment = $order->payments()->create(['payment_method_id' => $method->id, 'currency' => 'RUB',
                    'amount' => (float) $order->total, 'amount_paid' => 0, 'status' => 'pending']);
            }
            $payment->update(['status' => $paid ? 'paid' : 'cancelled',
                'amount_paid' => $paid ? (float) $payment->amount : 0,
                'status_message' => $paid ? 'Оплата при получении подтверждена оператором' : 'Ручная оплата отменена оператором']);
            return $payment;
        });
    }
}
