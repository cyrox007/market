<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Operator vocabulary and guarded fulfillment actions, independent of legacy shipping. */
class OperatorOrder
{
    public function paymentLabel(Order $order): string
    {
        $payments = $order->relationLoaded('payments') ? $order->payments : $order->payments()->get();
        if ($payments->contains(fn ($p) => $p->getRawOriginal('status') === 'paid' && (float) $p->amount_paid >= (float) $order->total)) { return 'Оплачено'; }
        if ($payments->contains(fn ($p) => $p->getRawOriginal('status') === 'partially_refunded')) { return 'Возвращено частично'; }
        if ($payments->contains(fn ($p) => $p->getRawOriginal('status') === 'refunded')) { return 'Возвращено'; }
        if ($payments->contains(fn ($p) => (float) $p->amount_paid > 0)) { return 'Оплачено частично'; }
        return in_array($order->checkoutPaymentMethod?->gateway, ['raiffeisen_ecom', 'raiffeisen_acquiring', 'sberbank_acquiring'], true)
            ? 'Ожидается оплата' : 'Оплата при получении';
    }

    public function warehouse(Order $order): string
    {
        $saved = $order->fulfillment_snapshot ?? [];
        return $saved['warehouse_name'] ?? $saved['name'] ?? $saved['warehouse_external_id'] ?? 'Не указан в сохранённом заказе';
    }

    public function address(Order $order): string
    {
        return $order->address_snapshot ?: ($order->checkout_snapshot['address']['full_address']
            ?? $order->fulfillment_snapshot['address_directory']['label'] ?? 'Не указан в сохранённом заказе');
    }

    public function integrationLabel(Order $order): string
    {
        if ($order->status === 'cancelled' && ! $order->integration_sent_at) { return 'Не требуется: заказ отменён'; }
        if ($order->cooling_until && now()->lt($order->cooling_until)) { return 'Будет передан после окончания ожидания'; }
        return match ($order->integration_state) {
            'sent' => 'Передан в систему учёта', 'failed' => 'Ошибка передачи — требуется проверка',
            'queued' => 'Ожидает передачи', default => 'Ещё не передан',
        };
    }

    public function next(Order $order): ?array
    {
        if ($order->cooling_until && now()->lt($order->cooling_until)) { return null; }
        return match ($order->status) {
            'assembling', 'accepted' => ['status' => 'assembled', 'label' => 'Заказ собран'],
            'assembled' => $order->delivery_type === 'pickup'
                ? ['status' => 'delivered', 'label' => 'Выдан покупателю']
                : ['status' => 'shipped', 'label' => 'Передан в доставку'],
            'shipped' => ['status' => 'in_transit', 'label' => 'Курьер выехал'],
            'in_transit' => ['status' => 'delivered', 'label' => 'Доставлен покупателю'],
            default => null,
        };
    }

    public function instruction(Order $order): string
    {
        if ($order->status === 'manager_processing') {
            return $order->cooling_until && now()->lt($order->cooling_until)
                ? 'Покупатель может отменить заказ до '.$order->cooling_until->format('d.m.Y H:i').'. Затем система автоматически проверит оплату и передаст заказ в сборку.'
                : 'Время ожидания истекло. Система должна проверить оплату и обработать заказ. Если статус не меняется, сообщите администратору.';
        }
        if ($order->status === 'cancelled') { return 'Заказ отменён. При полученной оплате проверьте возврат. Возобновление этого заказа запрещено.'; }
        if ($order->status === 'delivered') { return 'Заказ завершён. Возврат товара и денег оформляется отдельно.'; }
        if ($this->blockedReason($order)) { return $this->blockedReason($order); }
        return $this->next($order) ? 'Подтверждайте следующее действие только после его фактического выполнения.' : 'Исторический статус заказа. Для обработки обратитесь к администратору.';
    }

    public function blockedReason(Order $order): ?string
    {
        if (! $this->next($order)) { return null; }
        if ((in_array($order->checkoutPaymentMethod?->gateway, ['raiffeisen_ecom', 'raiffeisen_acquiring', 'sberbank_acquiring'], true)
            || $this->next($order)['status'] === 'delivered') && ! app(OrderCooling::class)->isPaid($order)) {
            return 'Сначала подтвердите получение оплаты. Банковскую оплату подтверждает банк; оплату при получении — оператор.';
        }
        return null;
    }

    public function advance(int $id, string $expected, int $userId): void
    {
        DB::transaction(function () use ($id, $expected, $userId): void {
            $order = Order::lockForUpdate()->findOrFail($id);
            \Illuminate\Support\Facades\Gate::authorize('update', $order);
            $next = $this->next($order);
            if (! $next || $next['status'] !== $expected) {
                throw ValidationException::withMessages(['status' => 'Заказ уже изменился. Обновите страницу и проверьте следующее действие.']);
            }
            if ($reason = $this->blockedReason($order)) {
                throw ValidationException::withMessages(['payment' => $reason]);
            }
            $order->changeStatus(OrderStatus::from($expected), $next['label'], $userId);
        });
    }

    public function cancel(int $id, string $reason, bool $afterDeadline = false): void
    {
        DB::transaction(function () use ($id, $reason, $afterDeadline): void {
            $order = Order::lockForUpdate()->findOrFail($id);
            \Illuminate\Support\Facades\Gate::authorize('update', $order);
            if ($afterDeadline) {
                abort_unless(auth()->user()?->hasRole('super_admin'), 403);
                if (in_array($order->status, ['cancelled', 'delivered'], true) || mb_strlen(trim($reason)) < 3) {
                    throw ValidationException::withMessages(['reason' => 'Укажите причину. Завершённый или отменённый заказ повторно отменять нельзя.']);
                }
            } elseif (! $order->canBeCancelled()) {
                throw ValidationException::withMessages(['status' => 'Время прямой отмены истекло. Требуется решение по обращению покупателя.']);
            }
            $order->changeStatus(OrderStatus::CANCELLED, $reason, auth()->id());
            if ($afterDeadline) {
                $order->cancellationRequest()->where('status', '!=', 'resolved')->update([
                    'status' => 'resolved', 'resolution' => 'Заказ отменён: '.$reason,
                ]);
            }
        });
    }
}
