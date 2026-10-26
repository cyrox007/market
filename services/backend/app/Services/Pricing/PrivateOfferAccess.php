<?php

namespace App\Services\Pricing;

use App\Models\Pricing\PrivatePriceLink;

class PrivateOfferAccess
{
    public function activate(string $token): PrivatePriceLink
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $link = PrivatePriceLink::where('token_hash', hash('sha256', $token))->first();
        abort_unless($link && $link->activeNow() && $link->product?->state == 'active', 404);
        $grants = session('private_offer_grants', []);
        $grants[$link->product_id] = $link->token_hash;
        session(['private_offer_grants' => array_slice($grants, -20, null, true)]);
        return $link;
    }

    public function grants(): \Illuminate\Support\Collection
    {
        // Public SSR product caches must never receive session-specific prices accidentally.
        if (request()->is('api/v1/products*') && request()->header('X-Private-Price-Context') !== '1') { return collect(); }
        if (! request()->hasSession()) { return collect(); }
        $hashes = array_values(request()->session()->get('private_offer_grants', []));
        if (! $hashes) { return collect(); }
        return PrivatePriceLink::whereIn('token_hash', $hashes)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where('expires_at', '>', now())->get()->keyBy('product_id');
    }

    public function price(int $skuId, float $publicPrice): float
    {
        $grant = $this->grants()->get($skuId);
        return $grant ? min($publicPrice, (float) $grant->price) : $publicPrice;
    }
}
