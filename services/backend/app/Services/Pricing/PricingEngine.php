<?php

namespace App\Services\Pricing;

use App\Models\Pricing\PricingCustomerGroup;
use App\Models\Pricing\PricingRule;
use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Models\User;
use App\Services\Product\CatalogPrice;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** One winning rule, applied to the regional price. Identical SQL for cards, facets and checkout. */
class PricingEngine
{
    public function customer(): ?User
    {
        return request()->attributes->has('pricing_customer') ? request()->attributes->get('pricing_customer') : request()->user();
    }

    public function eligible(?ShippingLocation $location, ?User $customer): Collection
    {
        $key = 'pricing:eligible:'.sha1(json_encode([$location?->id, $customer?->id, Cache::get('fulfillment_revision', '0'), now()->format('Y-m-d H:i:s')]));
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $rules = PricingRule::where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderByDesc('priority')->orderBy('id')->get();
        $locations = $location?->getAncestorsIds() ?? [];
        $eligible = $rules->filter(function (PricingRule $rule) use ($customer, $locations): bool {
            if ($rule->location_ids && ! array_intersect($locations, $rule->location_ids)) {
                return false;
            }

            return match ($rule->audience_type) {
                'all' => true, 'guests' => ! $customer, 'registered' => $customer !== null,
                'users' => $customer && in_array($customer->id, $rule->user_ids ?? []),
                'groups' => $customer && PricingCustomerGroup::whereIn('id', $rule->customer_group_ids ?? [])->get()->contains(fn ($group) => in_array($customer->id, $group->user_ids ?? [])),
                'conditions' => $customer && $this->conditions($rule, $customer),
                default => false,
            };
        });
        request()->attributes->set($key, $eligible);

        return $eligible;
    }

    private function conditions(PricingRule $rule, User $customer): bool
    {
        $conditions = array_filter($rule->conditions ?? [], fn ($v) => $v !== null && $v !== '');
        if (! $conditions) {
            return false;
        }
        // Only actually delivered orders contribute to loyalty; cancelled/unpaid orders do not.
        $stats = $customer->orders()->where('status', 'delivered')->selectRaw('COUNT(*) AS n, COALESCE(SUM(subtotal),0) AS spent')->first();

        return (! isset($conditions['min_orders']) || $stats->n >= $conditions['min_orders'])
            && (! isset($conditions['max_orders']) || $stats->n <= $conditions['max_orders'])
            && (! isset($conditions['min_spend']) || $stats->spent >= $conditions['min_spend'])
            && (! isset($conditions['min_account_days']) || ($customer->created_at && $customer->created_at <= now()->subDays((int) $conditions['min_account_days'])));
    }

    public function cacheContext(?ShippingLocation $location = null): string
    {
        $customer = $this->customer();

        // Include currently eligible windows, not a coarse time bucket: starts/ends act immediately.
        return sha1(json_encode([app(PrivateOfferAccess::class)->grants()->map(fn ($g) => [$g->id, $g->price, $g->updated_at]), $customer?->id, $this->eligible($location, $customer)->pluck('updated_at', 'id'),
            PricingRule::where('is_active', true)->get(['id', 'starts_at', 'ends_at'])->map(fn ($r) => [$r->id, $r->starts_at?->lte(now()), ! $r->ends_at || $r->ends_at->gt(now())]),
            Cache::get('fulfillment_revision', '0')]));
    }

    public function rows(Builder $base, ?ShippingLocation $location, ?User $customer, ?PricingRule $candidate = null): Builder
    {
        $query = DB::table('products')->joinSub($base, 'pricing_base', 'pricing_base.sku_id', '=', 'products.id')->selectRaw('products.id AS sku_id')
            ->selectRaw('CASE WHEN products.original_price > pricing_base.effective_price THEN products.original_price ELSE pricing_base.effective_price END AS reference_price');
        $priceCases = [];
        $idCases = [];
        $priceBindings = [];
        $idBindings = [];
        $rules = $this->eligible($location, $customer);
        if ($candidate) {
            $rules = $rules->reject(fn ($r) => $r->id === $candidate->id);
            $candidate = clone $candidate;
            $candidate->id ??= 2147483647;
            if ($candidate->is_active && (! $candidate->starts_at || $candidate->starts_at <= now()) && (! $candidate->ends_at || $candidate->ends_at > now())) {
                // Use the same audience predicate without persisting the draft.
                $audienceCopy = clone $candidate;
                $audienceCopy->location_ids = [];
                $allowed = match ($audienceCopy->audience_type) {
                    'all' => true, 'guests' => ! $customer, 'registered' => (bool) $customer,
                    'users' => $customer && in_array($customer->id, $candidate->user_ids ?? []),
                    'groups' => $customer && PricingCustomerGroup::whereIn('id', $candidate->customer_group_ids ?? [])->get()->contains(fn ($g) => in_array($customer->id, $g->user_ids ?? [])),
                    'conditions' => $customer && $this->conditions($candidate, $customer), default => false,
                };
                if ($allowed && (! $candidate->location_ids || array_intersect($location?->getAncestorsIds() ?? [], $candidate->location_ids))) {
                    $rules->push($candidate);
                }
            }
            $rules = $rules->sort(fn ($a, $b) => [-$a->priority, $a->id] <=> [-$b->priority, $b->id]);
        }
        foreach ($rules as $rule) {
            $target = app(PricingTargets::class)->skus($rule, $location)->toBase();
            $predicate = 'products.id IN ('.$target->toSql().')';
            $expression = match ($rule->adjustment_type) {
                'discount_percent' => 'pricing_base.effective_price * (1 - CAST(? AS DECIMAL(14,2)) / 100.0)',
                'markup_percent' => 'pricing_base.effective_price * (1 + CAST(? AS DECIMAL(14,2)) / 100.0)',
                'discount_fixed' => 'pricing_base.effective_price - CAST(? AS DECIMAL(14,2))',
                'markup_fixed' => 'pricing_base.effective_price + CAST(? AS DECIMAL(14,2))',
                'fixed_price' => 'CAST(? AS DECIMAL(14,2))',
            };
            // Portable non-negative clamp (MySQL + SQLite). Monetary rounding happens exactly once.
            $priceCases[] = 'WHEN '.$predicate.' THEN ROUND((('.$expression.') + ABS('.$expression.')) / 2.0, 2)';
            array_push($priceBindings, ...array_merge($target->getBindings(), [(float) $rule->amount, (float) $rule->amount]));
            $idCases[] = 'WHEN '.$predicate.' THEN ?';
            array_push($idBindings, ...array_merge($target->getBindings(), [$rule->id]));
        }

        $priceSql = $priceCases ? 'CASE '.implode(' ', $priceCases).' ELSE pricing_base.effective_price END' : 'pricing_base.effective_price';
        $grants = app(PrivateOfferAccess::class)->grants();
        if ($grants->isNotEmpty()) {
            $private = DB::table('private_price_links')->whereIn('id', $grants->pluck('id'))
                ->whereColumn('product_id', 'products.id')->select('price')->limit(1);
            $privateSql = $private->toSql();
            $priceSql = 'CASE WHEN ('.$privateSql.') < ('.$priceSql.') THEN ('.$privateSql.') ELSE ('.$priceSql.') END';
            $priceBindings = array_merge($private->getBindings(), $priceBindings, $private->getBindings(), $priceBindings);
        }

        return $query->selectRaw($priceSql.' AS effective_price', $priceBindings)
            ->selectRaw($idCases ? 'CASE '.implode(' ', $idCases).' ELSE NULL END AS pricing_rule_id' : 'NULL AS pricing_rule_id', $idBindings);
    }

    private function priceKey(?ShippingLocation $location): string
    {
        return 'pricing:sku_prices:'.sha1(json_encode([$location?->id, $this->customer()?->id, Cache::get('fulfillment_revision', '0'), now()->format('Y-m-d H:i:s')]));
    }

    public function preload(array $skuIds, ?ShippingLocation $location): void
    {
        if (! $skuIds || $this->eligible($location, $this->customer())->isEmpty()) {
            return;
        }
        $key = $this->priceKey($location);
        $known = request()->attributes->get($key, []);
        $missing = array_diff($skuIds, array_keys($known));
        if ($missing) {
            foreach (app(CatalogPrice::class)->rows($location)->whereIn('products.id', $missing)->get() as $row) {
                $known[$row->sku_id] = (float) $row->effective_price;
            }
            request()->attributes->set($key, $known);
        }
    }

    public function price(Product $sku, ?ShippingLocation $location): float
    {
        $this->preload([$sku->id], $location);

        return app(PrivateOfferAccess::class)->price($sku->id, request()->attributes->get($this->priceKey($location), [])[$sku->id] ?? (float) $sku->price);
    }

    public function oldPrice(Product $sku, ?ShippingLocation $location, float $price): ?float
    {
        if (app(PrivateOfferAccess::class)->grants()->has($sku->id)) { return null; }
        $regional = app(\App\Services\Product\ProductRegionRuleService::class)->getLegacyPriceForRegion($sku->parentProduct ?? $sku, $location, $sku->isVariant() ? $sku : null);
        $reference = max((float) ($sku->original_price ?? 0), $regional);

        return $reference > $price ? $reference : null;
    }

    public function explain(Product $sku, ?ShippingLocation $location = null, ?User $customer = null, bool $explicitCustomer = false, ?PricingRule $candidate = null): array
    {
        $customer = $explicitCustomer ? $customer : $this->customer();
        $base = app(CatalogPrice::class)->rows($location, false);
        $row = $this->rows($base, $location, $customer, $candidate)->where('products.id', $sku->id)->first();
        $regional = app(CatalogPrice::class)->rows($location, false)->where('products.id', $sku->id)->first()?->effective_price;
        $rule = isset($row->pricing_rule_id) ? (($candidate && $row->pricing_rule_id == ($candidate->id ?? 2147483647)) ? $candidate : PricingRule::find($row->pricing_rule_id)) : null;

        return ['private_offer_id' => app(PrivateOfferAccess::class)->grants()->get($sku->id)?->id,
            'base_price' => (float) $sku->getRawOriginal('price'), 'regional_price' => (float) $regional,
            'price' => app(PrivateOfferAccess::class)->price($sku->id, (float) ($row->effective_price ?? $regional)), 'currency' => 'RUB', 'rule_id' => $rule?->id,
            'rule_name' => $rule?->name, 'rule' => $rule?->only(['id', 'name', 'adjustment_type', 'amount', 'priority', 'updated_at']),
            'calculated_at' => now()->toISOString(), 'combination' => 'highest_priority_only'];
    }
}
