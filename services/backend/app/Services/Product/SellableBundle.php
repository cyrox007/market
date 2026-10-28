<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simple kit: physical root SKU + extras on root.
 * Variable kit: root is an editorial group, selected child offer is a VIRTUAL kit
 * and owns a complete mapping from every allowed component group to a real SKU.
 * Only real component SKUs enter pricing, shipping, stock, order items and 1C.
 */
class SellableBundle
{
    public function componentIsSellable(Product $sku, Product $owner): bool
    {
        if ($sku->is($owner) || (int) $sku->parent_product_id === (int) $owner->id) {
            return false;
        }
        // A kit or any kit offer must never be a physical component of another kit.
        if ($sku->is_sellable_bundle || $sku->parentProduct?->is_sellable_bundle
            || $sku->sellableBundleComponents()->exists()) {
            return false;
        }
        if ($sku->isVariable() && ! $sku->isVariant()) {
            return false;
        }
        return $sku->getRawOriginal('state') === Product::ACTIVE
            && (! $sku->isVariant() || $sku->parentProduct?->getRawOriginal('state') === Product::ACTIVE);
    }

    public function groupIsEligible(Product $group, Product $root): bool
    {
        return ! $group->isVariant() && ! $group->is($root)
            && $group->getRawOriginal('state') === Product::ACTIVE
            && ! $group->is_sellable_bundle && ! $group->sellableBundleComponents()->exists();
    }

    private function groupForSku(Product $sku): int
    {
        return (int) ($sku->parent_product_id ?: $sku->id);
    }

    /**
     * Called by the variation's own modal. Every selected root group must be mapped
     * to one concrete, active SKU; no silent matching by color name is allowed.
     */
    public function saveOfferComposition(Product $offer, array $rows): void
    {
        DB::transaction(function () use ($offer, $rows): void {
            $root = Product::query()->lockForUpdate()->findOrFail($offer->parent_product_id);
            if (! $root->isVariable() || (int) $offer->parent_product_id !== (int) $root->id) {
                throw ValidationException::withMessages(['components' => 'Выберите предложение вариативного комплекта.']);
            }
            $groups = $root->sellableBundleGroups()->lockForUpdate()->get();
            if ($groups->isEmpty() || $groups->count() > 29 || count($rows) !== $groups->count()) {
                throw ValidationException::withMessages(['components' => 'Сначала настройте группы составляющих в корневой карточке.']);
            }
            $allowed = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();
            $selected = [];
            foreach ($rows as $row) {
                $groupId = (int) ($row['group_id'] ?? 0);
                $sku = Product::with('parentProduct')->find($row['product_id'] ?? null);
                $qty = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (! in_array($groupId, $allowed, true) || isset($selected[$groupId])
                    || ! $sku || $this->groupForSku($sku) !== $groupId
                    || ! $this->componentIsSellable($sku, $root)
                    || ! $qty || $qty < 1 || $qty > 1000) {
                    throw ValidationException::withMessages(['components' => 'Для каждой группы выберите подходящий активный SKU и количество.']);
                }
                $selected[$groupId] = ['product_id' => (int) $sku->id, 'quantity' => $qty];
            }
            if (count($selected) !== count($allowed)) {
                throw ValidationException::withMessages(['components' => 'Каждая группа должна иметь своё торговое предложение.']);
            }
            $sync = [];
            foreach ($allowed as $i => $groupId) {
                $row = $selected[$groupId];
                $sync[$row['product_id']] = ['quantity' => $row['quantity'], 'sort_order' => $i];
            }
            $offer->sellableBundleComponents()->sync($sync);
            $offer->setRelation('parentProduct', $root);
            $this->syncBasePrice($offer);
            $root->flushCache();
            $offer->flushCache();
            Product::flushAllProductCaches();
        });
    }

    /**
     * Base catalogue price (before region/customer promotions) of the virtual offer.
     * Kept in products.price for search/listings; checkout always recalculates from
     * real SKUs through PricingEngine instead of trusting this denormalized value.
     */
    public function syncBasePrice(Product $offer): void
    {
        if (! $offer->isVariant() || ! $offer->parentProduct?->isVariable()) {
            return;
        }
        $rows = $offer->sellableBundleComponents()->get();
        if ($rows->isEmpty()) {
            return;
        }
        $price = $offer->bundle_price !== null ? (float) $offer->bundle_price
            : round((float) $rows->sum(fn (Product $sku) => (float) $sku->price * (int) $sku->pivot->quantity), 2);
        $offer->updateQuietly(['price' => $price, 'stock' => 0, 'backorder' => false]);
        Product::syncParentPriceFromVariants($offer->parentProduct);
    }

    public function syncOffersUsingComponent(int $componentSkuId): void
    {
        $offerIds = DB::table('sellable_bundle_components')
            ->where('component_product_id', $componentSkuId)->pluck('product_id');
        foreach (Product::with('parentProduct')->whereIn('id', $offerIds)->get() as $offer) {
            if ($offer->isVariant() && $offer->parentProduct?->isVariable()) {
                $this->syncBasePrice($offer);
            }
        }
    }

    /** @return array{bundle_id:int,primary_sku_id:int,bundle_quantity:int,items:array,components:array} */
    public function composition(int $bundleId, ?int $primarySkuId, int $quantity = 1): array
    {
        if ($quantity < 1 || $quantity > 1000) {
            throw ValidationException::withMessages(['quantity' => 'Количество комплекта должно быть от 1 до 1000.']);
        }
        $root = Product::query()->active()->whereNull('parent_product_id')
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->findOrFail($bundleId);
        if (! $root->is_sellable_bundle) {
            throw ValidationException::withMessages(['bundle_id' => 'Этот товар не продаётся комплектом.']);
        }
        $variable = $root->isVariable() && $root->variants()->exists();
        if ($variable) {
            $offer = $primarySkuId ? Product::query()->active()
                ->where('parent_product_id', $root->id)
                ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
                ->find($primarySkuId) : null;
            if (! $offer) {
                throw ValidationException::withMessages(['primary_sku_id' => 'Выберите торговое предложение комплекта.']);
            }
            $groups = $root->sellableBundleGroups()
                ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
            if ($groups->isEmpty() || $groups->count() > 29) {
                throw ValidationException::withMessages(['bundle_id' => 'Для комплекта не настроены группы составляющих.']);
            }
            $parts = $offer->sellableBundleComponents()->with('parentProduct')
                ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
            if ($parts->count() !== $groups->count()) {
                throw ValidationException::withMessages(['primary_sku_id' => 'Состав этого торгового предложения не заполнен.']);
            }
            $allowedGroups = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();
            $seen = [];
            $items = [];
            $components = [];
            foreach ($parts as $sku) {
                $groupId = $this->groupForSku($sku);
                if (! in_array($groupId, $allowedGroups, true) || isset($seen[$groupId])
                    || ! $this->componentIsSellable($sku, $root)) {
                    throw ValidationException::withMessages(['primary_sku_id' => 'Состав торгового предложения устарел. Обновите его в админке.']);
                }
                $seen[$groupId] = true;
                $this->appendComponent($items, $components, $sku, (int) $sku->pivot->quantity, $quantity, $groupId);
            }
            if (count($seen) !== count($allowedGroups)) {
                throw ValidationException::withMessages(['primary_sku_id' => 'Не все группы составляющих настроены.']);
            }
            // Neither the parent nor the virtual bundle offer has its own stock or 1C line.
            return ['bundle_id' => (int) $root->id, 'primary_sku_id' => (int) $offer->id,
                'bundle_quantity' => $quantity, 'items' => $items, 'components' => $components];
        }

        if ($primarySkuId !== null && $primarySkuId !== (int) $root->id) {
            throw ValidationException::withMessages(['primary_sku_id' => 'Неверный SKU основного товара.']);
        }
        $items = [];
        $components = [];
        $this->appendComponent($items, $components, $root, 1, $quantity, (int) $root->id, 'primary');
        $parts = $root->sellableBundleComponents()->with('parentProduct')
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
        if ($parts->isEmpty() || $parts->count() > 29) {
            throw ValidationException::withMessages(['bundle_id' => 'Не задан корректный состав комплекта.']);
        }
        foreach ($parts as $sku) {
            if (! $this->componentIsSellable($sku, $root)) {
                throw ValidationException::withMessages(['bundle_id' => 'Компонент недоступен.']);
            }
            $this->appendComponent($items, $components, $sku, (int) $sku->pivot->quantity, $quantity);
        }
        return ['bundle_id' => (int) $root->id, 'primary_sku_id' => (int) $root->id,
            'bundle_quantity' => $quantity, 'items' => $items, 'components' => $components];
    }

    private function appendComponent(array &$items, array &$components, Product $sku, int $units, int $sets,
        ?int $groupId = null, string $role = 'component'): void
    {
        if ($units < 1 || $units > 1000 || $units * $sets > 1000) {
            throw ValidationException::withMessages(['quantity' => 'Количество компонентов превышает допустимое.']);
        }
        $items[] = ['product_id' => (int) $sku->id, 'quantity' => $units * $sets];
        $components[] = [
            'product_id' => (int) $sku->id, 'sku' => (string) $sku->sku, 'name' => (string) $sku->name,
            'component_group_id' => $groupId, 'quantity_per_bundle' => $units, 'role' => $role,
        ];
    }

    public function quote(int $bundleId, ?int $primarySkuId, int $quantity = 1, ?ShippingLocation $region = null): array
    {
        $composition = $this->composition($bundleId, $primarySkuId, $quantity);
        $skus = Product::with('parentProduct')->whereIn('id', array_column($composition['items'], 'product_id'))->get()->keyBy('id');
        $base = app(\App\Services\Pricing\PrivateOfferAccess::class)->pricedItems(
            app(\App\Services\Order\DirectCheckout::class)->items($composition['items']), $region);
        $lines = app(BundlePricing::class)->apply($composition, $base);
        $rows = collect($lines)->groupBy('product_id')->map(function ($group, $id) use ($skus): array {
            $quantity = $group->sum('quantity');
            $total = round($group->sum(fn ($row) => $row['price'] * $row['quantity']), 2);
            return ['product_id' => (int) $id, 'sku' => $skus[$id]->sku, 'name' => $skus[$id]->name,
                'quantity' => $quantity, 'unit_price' => $total / $quantity, 'total' => $total,
                'price_allocations' => $group->map(fn ($row) => ['quantity' => $row['quantity'], 'unit_price' => $row['price']])->values()->all()];
        })->values()->all();
        return [...$composition, 'subtotal' => round(array_sum(array_column($rows, 'total')), 2), 'priced_components' => $rows];
    }

    /**
     * Coarse catalogue availability on ONE warehouse for the whole composition.
     * Destination-specific delivery/pickup is rechecked by WarehouseDeliveryQuote
     * and locked by WarehouseOrderStock in the order transaction.
     */
    public function availableSets(array $items): int
    {
        if (! config('bundles.multi_warehouse_enabled', false)) {
            return $this->singleWarehouseAvailableSets($items);
        }
        if ($items === []) {
            return 0;
        }
        // Every concrete SKU has its own stock. Different SKU groups may be
        // sourced from different warehouses; NEVER count the virtual kit offer.
        // Sum physical free units before dividing by the quantity per set.
        $units = collect($items)->groupBy('product_id')->map(fn ($rows): int => (int) $rows->sum('quantity'));
        $warehouses = \App\Models\Inventory\Warehouse::query()->where('is_active', true)
            ->with([
                'productStocks' => fn ($q) => $q->whereIn('product_id', $units->keys()),
                'productAvailabilities' => fn ($q) => $q->whereIn('product_id', $units->keys()),
            ])->get();
        $backorderIds = Product::whereIn('id', $units->keys())->where('backorder', true)->pluck('id')->flip();
        $availableSets = PHP_INT_MAX;
        foreach ($units as $skuId => $unitsPerSet) {
            $bestForSku = 0;
            $freeUnits = 0;
            foreach ($warehouses as $warehouse) {
                if ($warehouse->stock_mode === 'availability') {
                    $source = $warehouse->productAvailabilities->firstWhere('product_id', $skuId);
                    if ($source?->available_to_order) {
                        // Factory availability is a boolean, not a finite count.
                        $bestForSku = max($bestForSku, 1);
                    }
                } elseif ($warehouse->stock_mode === 'quantity') {
                    $stock = $warehouse->productStocks->firstWhere('product_id', $skuId);
                    if ($stock) {
                        $free = app(\App\Services\Inventory\CartStockReservations::class)
                            ->available((int) $warehouse->id, (int) $skuId, (float) $stock->quantity);
                        $freeUnits += max(0, (int) floor($free));
                    }
                }
            }
            // 1 is a catalogue orderability signal, not a fictitious physical unit.
            $availableSets = min($availableSets, max(
                $bestForSku, (int) floor($freeUnits / $unitsPerSet),
                $backorderIds->has((int) $skuId)
                    && $warehouses->contains(fn ($w): bool =>
                        $w->productStocks->contains('product_id', $skuId)
                        || $w->productAvailabilities->contains('product_id', $skuId)) ? 1 : 0
            ));
            if ($availableSets === 0) {
                return 0;
            }
        }
        return $availableSets === PHP_INT_MAX ? 0 : $availableSets;
    }

    private function singleWarehouseAvailableSets(array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $units = collect($items)->groupBy('product_id')->map(fn ($rows): int => (int) $rows->sum('quantity'));
        $warehouses = \App\Models\Inventory\Warehouse::query()->where('is_active', true)
            ->with([
                'productStocks' => fn ($q) => $q->whereIn('product_id', $units->keys()),
                'productAvailabilities' => fn ($q) => $q->whereIn('product_id', $units->keys()),
            ])->get();
        $backorderIds = Product::whereIn('id', $units->keys())->where('backorder', true)->pluck('id')->flip();
        $best = 0;
        foreach ($warehouses as $warehouse) {
            if ($warehouse->stock_mode === 'availability') {
                $available = $warehouse->productAvailabilities->keyBy('product_id');
                if ($units->every(fn ($qty, $id): bool => (bool) ($available[$id]->available_to_order ?? false)
                    || ($backorderIds->has((int) $id) && isset($available[$id])))) {
                    $best = max($best, 1);
                }
                continue;
            }
            if ($warehouse->stock_mode !== 'quantity') {
                continue;
            }
            $stocks = $warehouse->productStocks->keyBy('product_id');
            $sets = PHP_INT_MAX;
            foreach ($units as $id => $qty) {
                $stock = $stocks->get($id);
                if ($backorderIds->has((int) $id) && $stock) {
                    // No finite inventory bound for an explicitly preordered SKU.
                    continue;
                }
                if (! $stock) {
                    $sets = 0;
                    break;
                }
                $available = app(\App\Services\Inventory\CartStockReservations::class)
                    ->available((int) $warehouse->id, (int) $id, (float) $stock->quantity);
                $sets = min($sets, (int) floor($available / $qty));
            }
            $best = max($best, $sets === PHP_INT_MAX ? 1 : $sets);
        }
        return max(0, $best);
    }


    public function assertMatches(array $composition, array $submitted): void
    {
        $normalize = static function (array $items): array {
            $amounts = [];
            foreach ($items as $item) {
                $id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
                $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || ! $qty || $qty < 1 || $qty > 1000) {
                    throw ValidationException::withMessages(['items' => 'Неверный состав комплекта.']);
                }
                $amounts[$id] = ($amounts[$id] ?? 0) + $qty;
            }
            ksort($amounts);
            return $amounts;
        };
        if ($normalize($composition['items']) !== $normalize($submitted)) {
            throw ValidationException::withMessages(['items' => 'Состав комплекта изменился. Обновите оформление заказа.']);
        }
    }
}
