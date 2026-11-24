<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Support\Integration\OrderOneCSyncDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** An isolated bank-state store. Only the normal callback job can mark shop payments paid. */
final class LocalSberSimulator
{
    public function assertEnabled(): void
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        abort_unless(config('local_sber.enabled') && app()->environment(['local', 'testing'])
            && (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local'))
            && ! OrderOneCSyncDispatcher::isEnabled(), 404);
    }

    public static function isSimulatedId(string $id): bool
    {
        return str_starts_with($id, '00000000-');
    }

    public function register(Order $order, float $rubles, string $number): array
    {
        $this->assertEnabled();
        abort_unless(is_finite($rubles) && $rubles > 0, 422);
        $id = '00000000-'.substr((string) Str::uuid(), 9);
        DB::table('local_sber_payments')->insert([
            'id' => $id, 'order_id' => $order->id, 'order_number' => $number,
            'amount' => (int) round($rubles * 100), 'status' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true, 'remote_id' => $id,
            'form_url' => URL::temporarySignedRoute('local-sber.show', now()->addHours(24), ['id' => $id])];
    }

    public function find(string $id): object
    {
        $this->assertEnabled();
        abort_unless(self::isSimulatedId($id), 404);
        return DB::table('local_sber_payments')->where('id', $id)->first() ?? abort(404);
    }

    public function status(string $id, string $orderNumber = ''): array
    {
        $this->assertEnabled();
        $row = $id !== '' ? $this->find($id) : DB::table('local_sber_payments')->where('order_number', $orderNumber)->first();
        if (! $row) { return []; }
        if ($row->unreachable) {
            return [];
        }
        return ['errorCode' => 0, 'orderId' => $row->id, 'orderNumber' => $row->order_number,
            'orderStatus' => (int) $row->status, 'amount' => (int) $row->amount,
            'paymentAmountInfo' => ['refundedAmount' => (int) $row->refunded_amount]];
    }

    public function decide(string $id, string $scenario): void
    {
        $this->find($id);
        DB::transaction(function () use ($id, $scenario): void {
            $row = DB::table('local_sber_payments')->where('id', $id)->lockForUpdate()->first();
            // Repeated success is allowed to exercise duplicate callbacks; captured money cannot become unpaid.
            abort_if(in_array((int) $row->status, [2, 4], true) && $scenario !== 'success', 409);
            abort_if((int) $row->status === 4, 409);
            DB::table('local_sber_payments')->where('id', $id)->update([
                'status' => match ($scenario) { 'success' => 2, 'decline' => 6, 'cancel' => 3, default => 0 },
                'unreachable' => $scenario === 'timeout', 'updated_at' => now(),
            ]);
        });
    }

    public function refund(Order $order, string $id, float $rubles, string $refundId): array
    {
        $this->find($id);
        return DB::transaction(function () use ($order, $id, $rubles, $refundId): array {
            $row = DB::table('local_sber_payments')->where('id', $id)->lockForUpdate()->first();
            if ($row->unreachable) {
                return ['ok' => false, 'error' => 'bank_unconfirmed'];
            }
            $amount = (int) round($rubles * 100);
            if ((int) $row->order_id !== (int) $order->id || ! is_finite($rubles) || $amount <= 0
                || $amount !== (int) $row->amount || ! in_array((int) $row->status, [2, 4], true)
                || ($row->refund_id !== null && $row->refund_id !== $refundId)) {
                return ['ok' => false, 'error' => 'invalid_simulated_refund'];
            }
            DB::table('local_sber_payments')->where('id', $id)->update([
                'status' => 4, 'refunded_amount' => $amount, 'refund_id' => $refundId, 'updated_at' => now(),
            ]);
            return ['ok' => true];
        });
    }
}
