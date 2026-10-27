<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckoutIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post') || $request->is('*/orders/one-click')) { return $next($request); }
        $key = $request->header('Idempotency-Key');
        if ($key === null) {
            return response()->json(['message' => 'Передайте уникальный Idempotency-Key для оформления заказа.'], 422);
        }
        if (! is_string($key) || strlen($key) < 16 || strlen($key) > 128) { abort(422, 'Invalid Idempotency-Key'); }
        $owner = app(\App\Services\Inventory\CartStockReservations::class)->owner();
        $fingerprint = hash('sha256', json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $hash = hash('sha256', $owner.':'.$key);
        DB::beginTransaction();
        try {
            DB::table('checkout_attempts')->insertOrIgnore(['key_hash' => $hash, 'owner_hash' => $owner,
                'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $attempt = DB::table('checkout_attempts')->where('key_hash', $hash)->lockForUpdate()->first();
            if (! hash_equals($attempt->fingerprint, $fingerprint)) { DB::rollBack(); return response()->json(['message' => 'Ключ оформления использован для другого заказа.'], 409); }
            if ($attempt->response) {
                $data = json_decode(Crypt::decryptString($attempt->response), true, 512, JSON_THROW_ON_ERROR);
                if ($order = \App\Models\Order\Order::find($attempt->order_id)) {
                    $data['order'] = (new \App\Http\Resources\OrderResource($order->load(['items.product', 'statusHistory', 'address'])))->resolve($request);
                    if (! $order->canPayOnline()) { unset($data['gateway_client_config']); }
                }
                DB::commit();
                return response()->json($data, 201)->header('Idempotency-Replayed', 'true')->header('Cache-Control', 'no-store, private');
            }
            $response = $next($request);
            if ($response->getStatusCode() >= 400) { DB::rollBack(); return $response; }
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            DB::table('checkout_attempts')->where('id', $attempt->id)->update(['order_id' => $data['order']['id'] ?? null,
                'response' => Crypt::encryptString($response->getContent()), 'updated_at' => now()]);
            DB::commit();
            return $response;
        } catch (\Throwable $e) { DB::rollBack(); throw $e; }
    }
}
