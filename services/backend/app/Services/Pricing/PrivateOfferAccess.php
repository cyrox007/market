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
        if (! request()->hasSession()) { return collect(); }
        $scoped = request()->attributes->has('private_offer_line_hashes');
        $hashes = $scoped ? request()->attributes->get('private_offer_line_hashes') : array_values(request()->session()->get('private_offer_grants', []));
        if (! $hashes) { return collect(); }
        $query = PrivatePriceLink::whereIn('token_hash', $hashes)->where('is_active', true);
        if (! $scoped && !request()->is('api/v1/private-offers/current')) {
            $id = request()->header('X-Private-Price-Context');
            if (!is_string($id) || !ctype_digit($id)) { return collect(); }
            $query->whereKey((int) $id);
        }
        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where('expires_at', '>', now())->get()->keyBy('product_id');
    }

    public function withItem(object $item, callable $callback): mixed
    {
        // Real cart rows retain their own price source. Direct checkout uses the explicit link context.
        if (! $item instanceof \Illuminate\Database\Eloquent\Model) { return $callback(); }
        $attributes = request()->attributes;
        $had = $attributes->has('private_offer_line_hashes');
        $previous = $attributes->get('private_offer_line_hashes');
        $hash = ($item->configuration ?? [])['private_offer_hash'] ?? null;
        $attributes->set('private_offer_line_hashes', $hash ? [$hash] : []);
        try { return $callback(); }
        finally { if ($had) $attributes->set('private_offer_line_hashes', $previous); else $attributes->remove('private_offer_line_hashes'); }
    }

    public function pricedItems(iterable $items, ?\App\Models\Shipping\ShippingLocation $region): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = $this->withItem($item, function () use ($item, $region): array {
                $product = $item->buyable ?? \App\Models\Product\Product::findOrFail($item->product_id);
                $snapshot = app(PricingEngine::class)->explain($product, $region);
                return ['product_id' => $product->id, 'quantity' => $item->quantity,
                    'price' => $snapshot['price'], 'pricing_snapshot' => $snapshot,
                    'expired_offer' => (filled(($item->configuration ?? [])['private_offer_hash'] ?? null)
                        || (!$item instanceof \Illuminate\Database\Eloquent\Model && filled(request()->header('X-Private-Price-Context')) && isset(session('private_offer_grants', [])[$product->id])))
                        && !$this->grants()->has($product->id)];
            });
        }
        return $result;
    }

    public function price(int $skuId, float $publicPrice): float
    {
        $grant = $this->grants()->get($skuId);
        return $grant ? min($publicPrice, (float) $grant->price) : $publicPrice;
    }
}
