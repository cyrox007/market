<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Payment\Gateways\SberbankAcquiringGateway;
use App\Jobs\ProcessSberbankCallbackJob;
use App\Contracts\Gateway\GatewayLoggerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A payment row represents exactly one bank registration. Never overwrite an
 * existing bank ID, or resend an ambiguous registration with a fresh key.
 */
final class SberbankPaymentAttempts
{
    public function prepare(Order $order, $payment): void
    {
        $data = $payment->data ?? [];
        if (isset($data['sberbank_registration']) || filled($payment->remote_id)) { return; }
        $data['sberbank_registration'] = [
            'number' => $order->number . '-S' . $payment->id,
            'key' => (string) Str::uuid(),
            'state' => 'prepared',
        ];
        $payment->data = $data;
        $payment->saveOrFail();
    }

    public function cachedConfig(Order $order): ?array
    {
        $payment = $order->getLatestPayment();
        if (! $payment || ! $payment->status->isPending() || ! filled($payment->remote_id)) { return null; }
        return $this->formConfig($payment);
    }

    private function formConfig($payment): ?array
    {
        if (LocalSberSimulator::isSimulatedId((string) $payment->remote_id) !== (bool) config('local_sber.enabled')) { return null; }
        $url = $payment->data['sberbank_form_url'] ?? null;
        return is_string($url) && $url !== '' ? ['useEcomApi' => true, 'payformUrl' => $url] : null;
    }

    public function config(Order $order, bool $retry): ?array
    {
        if (config('local_sber.enabled')) { app(LocalSberSimulator::class)->assertEnabled(); }
        $lock = Cache::lock('sber-registration:'.$order->id, 90);
        if (! $lock->get()) { return null; }
        try {
            $order = $order->fresh();
            if (! $this->eligible($order)) { return null; }
            $payment = $order->getLatestPayment();
            if (! $payment || ! ($payment->getMethod()->getGateway() instanceof SberbankAcquiringGateway)) { return null; }
            if (filled($payment->remote_id)) {
                if (config('local_sber.enabled')) {
                    abort_unless(LocalSberSimulator::isSimulatedId((string) $payment->remote_id), 409);
                }
                if (! config('local_sber.enabled') && LocalSberSimulator::isSimulatedId((string) $payment->remote_id)) { return null; }
                $cached = $this->cachedConfig($order);
                if (! $retry) { return $cached; }
                // A declined authorization may still be retried on the same
                // form. Only reversal or the bank's expired-session code is
                // sufficient to issue a DIFFERENT bank registration.
                $bank = app(SberbankAcquiringClient::class)->getOrderStatusExtended((string) $payment->remote_id, (string) $order->number);
                $status = filter_var($bank['orderStatus'] ?? null, FILTER_VALIDATE_INT);
                $error = filter_var($bank['errorCode'] ?? null, FILTER_VALIDATE_INT);
                $action = filter_var($bank['actionCode'] ?? null, FILTER_VALIDATE_INT);
                if ($error !== 0) { return $cached; }
                if ($status === 0 || ($status === 6 && $action !== -2007)) {
                    return $this->formConfig($payment);
                }
                if ($status === 2) {
                    $this->reconcileOrder($order);
                    return null;
                }
                if ($status !== 3 && ! ($status === 6 && $action === -2007)) { return null; }
                $payment = $this->newAttempt($order, $payment);
                if (! $payment) { return null; }
            }
            if (($payment->data['sberbank_registration']['state'] ?? null) === 'rejected') {
                if (! $retry) { return null; }
                $payment = $this->newAttempt($order, $payment);
                if (! $payment) { return null; }
            }
            $this->prepare($order, $payment);
            $intent = $payment->fresh()->data['sberbank_registration'];
            if ($intent['state'] !== 'prepared') {
                if ($this->recover($order, $payment->fresh()) && ! $this->reconcileOrder($order)) {
                    return null;
                }
                return $this->eligible($order->fresh()) ? $this->cachedConfig($order->fresh()) : null;
            }
            // Commit the send marker before touching the bank. A crash between
            // here and the POST is ambiguous and must be reconciled, not resent.
            $claimed = DB::transaction(function () use ($order, $payment) {
                $fresh = Order::lockForUpdate()->findOrFail($order->id);
                $row = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
                $data = $row->data;
                if (! $this->eligible($fresh) || $data['sberbank_registration']['state'] !== 'prepared') { return false; }
                $data['sberbank_registration']['state'] = 'submitted';
                $row->data = $data;
                $row->saveOrFail();
                return true;
            });
            if (! $claimed) { return null; }
            $frontend = rtrim((string) config('app.frontend_url'), '/');
            try {
                $result = app(SberbankAcquiringClient::class)->registerOrder($order, (float) $payment->amount,
                    $frontend.'/orders/'.$order->id.'?payment=success',
                    $frontend.'/orders/'.$order->id.'?payment=fail', $intent['number'], $intent['key']);
                if (! ($result['ok'] ?? false)) {
                    if ($result['registration_rejected'] ?? false) {
                        $this->reject($payment);
                    }
                    return null;
                }
                $this->bind($payment, (string) ($result['remote_id'] ?? ''), (string) ($result['form_url'] ?? ''));
            } catch (\Throwable $e) {
                report($e);
                return null;
            }
            return $this->eligible($order->fresh()) ? $this->cachedConfig($order->fresh()) : null;
        } finally { $lock->release(); }
    }

    private function newAttempt(Order $order, $payment)
    {
        return DB::transaction(function () use ($order, $payment) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);
            if (! $this->eligible($fresh) || $fresh->getLatestPayment()?->id !== $payment->id) { return null; }
            $next = $fresh->payments()->create([
                'payment_method_id' => $payment->payment_method_id,
                'currency' => $payment->currency, 'amount' => $payment->amount,
                'amount_paid' => 0, 'status' => 'pending',
            ]);
            $this->prepare($fresh, $next);
            return $next;
        });
    }

    private function reject($payment): void
    {
        DB::transaction(function () use ($payment): void {
            Order::lockForUpdate()->findOrFail($payment->payable_id);
            $row = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            $data = $row->data;
            if (filled($row->remote_id) || $data['sberbank_registration']['state'] !== 'submitted') { return; }
            $data['sberbank_registration']['state'] = 'rejected';
            $row->data = $data;
            $row->saveOrFail();
        });
    }

    private function eligible(Order $order): bool
    {
        return in_array($order->status, ['new', 'awaiting_payment', 'manager_processing'], true)
            && (! $order->cooling_until || now()->lt($order->cooling_until))
            && ! app(\App\Services\Order\OrderCooling::class)->isPaid($order);
    }

    private function bind($payment, string $id, string $url): void
    {
        if (! Str::isUuid($id)) { throw new \RuntimeException('Invalid Sber registration ID'); }
        DB::transaction(function () use ($payment, $id, $url): void {
            Order::lockForUpdate()->findOrFail($payment->payable_id);
            $row = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
            if (filled($row->remote_id) && $row->remote_id !== $id) { throw new \LogicException('Bank ID is immutable'); }
            if ($row->newQuery()->where('remote_id', $id)->where('id', '!=', $row->id)->exists()) {
                throw new \LogicException('Bank ID already bound');
            }
            $data = $row->data;
            $data['sberbank_registration']['state'] = 'bound';
            if ($url !== '') { $data['sberbank_form_url'] = $url; }
            $row->remote_id = $id;
            $row->sberbank_bank_id = $id;
            $row->data = $data;
            $row->saveOrFail();
        });
    }

    /** Find a lost registration by the exact persisted merchant number. Never trust callback IDs here. */
    public function recover(Order $order, $payment, ?string $hint = null): bool
    {
        $intent = $payment->data['sberbank_registration'] ?? null;
        if (! $intent || $intent['state'] !== 'submitted') { return false; }
        $bank = app(SberbankAcquiringClient::class)->getOrderStatusExtended($hint ?? '', $intent['number']);
        if (filter_var($bank['errorCode'] ?? null, FILTER_VALIDATE_INT) !== 0
            || (string) ($bank['orderNumber'] ?? '') !== $intent['number']
            || filter_var($bank['amount'] ?? null, FILTER_VALIDATE_INT) !== (int) round((float) $payment->amount * 100)) { return false; }
        $id = $bank['orderId'] ?? $bank['mdOrder'] ?? null;
        foreach ($bank['attributes'] ?? [] as $attribute) {
            if (($attribute['name'] ?? null) === 'mdOrder') { $id ??= $attribute['value'] ?? null; }
        }
        $id ??= $hint;
        if (! is_string($id) || ! Str::isUuid($id) || ($hint !== null && $id !== $hint)) { return false; }
        $url = config('local_sber.enabled')
            ? \Illuminate\Support\Facades\URL::temporarySignedRoute('local-sber.show', now()->addHours(24), ['id' => $id]) : '';
        if (! config('local_sber.enabled')) {
            $url = rtrim((string) config('payment.sberbank_acquiring.base_url'), '/')
                . config('payment.sberbank_acquiring.pay_page_path', '/pp/pay_ru') . '?orderId=' . rawurlencode($id);
        }
        $this->bind($payment, $id, $url);
        return true;
    }

    /** Reconcile ALL attempts, including earlier ones and late settlements on cancelled orders. */
    public function reconcileOrder(Order $order): bool
    {
        $complete = true;
        foreach ($order->payments()->get() as $payment) {
            try {
            if (! ($payment->getMethod()->getGateway() instanceof SberbankAcquiringGateway)) { continue; }
            if (in_array((string) $payment->getRawOriginal('status'), ['paid', 'refunded', 'partially_refunded'], true)) { continue; }
            if (! filled($payment->remote_id)) {
                $state = $payment->data['sberbank_registration']['state'] ?? null;
                if ($state !== 'submitted') { continue; }
                if (! $this->recover($order, $payment)) { $complete = false; continue; }
                $payment->refresh();
            }
            $job = new ProcessSberbankCallbackJob(['mdOrder' => $payment->remote_id, 'orderNumber' => $order->number]);
            if (! $job->reconcile(app(GatewayLoggerInterface::class))) { $complete = false; }
            } catch (\Throwable $e) {
                report($e);
                $complete = false;
            }
        }
        if (! $complete) { Log::warning('Sber order reconciliation incomplete', ['order_id' => $order->id]); }
        return $complete;
    }
}
