<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSberbankCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Untrusted bank notifications are triggers only; the job verifies the bank. */
class SberbankCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Bound input and normalize aliases before generating the deduplication key.
        $id = $request->input('mdOrder', $request->input('orderId'));
        $number = $request->input('orderNumber', $request->input('order_number'));
        $operation = $request->input('operation', '');
        $status = $request->input('status', '');
        if (($id !== null && (! is_string($id) || ! Str::isUuid($id)))
            || ($number !== null && (! is_string($number) || ! preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $number)))
            || ($id === null && $number === null)
            || ! is_string($operation) || strlen($operation) > 64
            || (! is_string($status) && ! is_int($status)) || strlen((string) $status) > 32) {
            return response()->json(['message' => 'Invalid callback'], 422);
        }
        $id = $id !== null ? strtolower($id) : null;
        $payload = ['mdOrder' => $id, 'orderNumber' => $number,
            'operation' => strtolower(trim($operation)), 'status' => trim((string) $status)];
        // A short, atomic reservation suppresses concurrent deliveries. It is
        // not a permanent event ledger: later events and scheduled reconciliation
        // must still be able to recover a payment if the queue/worker fails.
        $key = 'sber-callback:v1:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $ttl = max(10, min(300, (int) config('payment.sberbank_acquiring.callback_dedup_seconds', 60)));
        if (! Cache::add($key, true, $ttl)) {
            return response()->json(['status' => 'ok'], 200);
        }
        try {
            ProcessSberbankCallbackJob::dispatch($payload + ['_request_ip' => $request->ip()]);
        } catch (\Throwable $e) {
            Cache::forget($key);
            report($e);
            return response()->json(['message' => 'Callback queue unavailable'], 503);
        }
        return response()->json(['status' => 'ok'], 200);
    }
}
