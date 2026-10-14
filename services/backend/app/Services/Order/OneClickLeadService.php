<?php

namespace App\Services\Order;

use App\Models\Order\OneClickLead;
use App\Models\Product\Product;
use App\Services\Shipping\FulfillmentLines;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OneClickLeadService
{
    public function create(array $data, ?string $idempotencyKey, string $ip): array
    {
        $line = app(FulfillmentLines::class)->resolve([$data])[0];
        $fingerprint = hash('sha256', json_encode([$data['name'], $data['phone'], $data['comment'] ?? null, $line, $data['region_id'] ?? null]));
        $key = $idempotencyKey ? hash('sha256', $idempotencyKey) : null;
        $existing = $key ? OneClickLead::where('idempotency_key', $key)->first() : null;
        if ($existing && $existing->fingerprint !== $fingerprint) {
            throw new HttpResponseException(response()->json(['message' => 'Idempotency-Key уже использован для другой заявки.'], 409));
        }
        $existing ??= OneClickLead::where('fingerprint', $fingerprint)->where('created_at', '>=', now()->subMinutes(10))->first();
        if ($existing) {
            return [$existing, false];
        }
        $phoneKey = 'one-click:phone:'.hash('sha256', $data['phone']);
        if (RateLimiter::tooManyAttempts($phoneKey, 5)) {
            throw new HttpResponseException(response()->json(['message' => 'Слишком много новых заявок на этот телефон. Повторите позже.'], 429)->header('Retry-After', RateLimiter::availableIn($phoneKey)));
        }
        $sku = Product::findOrFail($line['product_id']);
        try {
            $lead = OneClickLead::create(['number' => 'OC-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'name' => $data['name'], 'phone' => $data['phone'], 'comment' => $data['comment'] ?? null,
                'product_id' => $sku->id, 'quantity' => $line['quantity'], 'shipping_location_id' => $data['region_id'] ?? null,
                'product_snapshot' => $sku->only(['id', 'parent_product_id', 'name', 'sku', 'price']),
                'consents' => ['offer' => true, 'personal_data' => true, 'accepted_at' => now()->toISOString(),
                    'ip_hash' => hash_hmac('sha256', $ip, (string) config('app.key'))],
                'fingerprint' => $fingerprint, 'dedup_key' => hash('sha256', $fingerprint.':'.intdiv(now()->timestamp, 600)), 'idempotency_key' => $key]);
        } catch (UniqueConstraintViolationException $error) {
            $conflicting = $key ? OneClickLead::where('idempotency_key', $key)->first() : null;
            if ($conflicting && $conflicting->fingerprint !== $fingerprint) {
                throw new HttpResponseException(response()->json(['message' => 'Idempotency-Key уже использован для другой заявки.'], 409));
            }
            $lead = OneClickLead::where('fingerprint', $fingerprint)->where('created_at', '>=', now()->subMinutes(10))->first();
            if (! $lead) {
                throw $error;
            }

            return [$lead, false];
        }
        RateLimiter::hit($phoneKey, 600);

        return [$lead, true];
    }
}
