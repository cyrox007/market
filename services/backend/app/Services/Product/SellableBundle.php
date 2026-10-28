<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sellable kit is the selected primary SKU plus fixed, concrete add-on SKUs.
 * All lines are expanded before warehouse quoting, reservation and 1C sync.
 * Never use min-price of a variable parent as the price of a kit component.
 */
class SellableBundle
{
    public function componentIsSellable(Product $sku, Product $owner): bool
    {
        if ($sku->is($owner) || (int) $sku->parent_product_id === (int) $owner->id) {
            return false;
        }
        if ((bool) $sku->is_sellable_bundle || $sku->sellableBundleComponents()->exists()) {
            return false; // nested sets are intentionally not supported
        }
        if ($sku->isVariable() && ! $sku->isVariant() && $sku->variants()->exists()) {
            return false; // concrete variant required
        }

        return $sku->getRawOriginal('state') === Product::ACTIVE
            && (! $sku->isVariant() || $sku->parentProduct?->getRawOriginal('state') === Product::ACTIVE);
    }

    /**
     * @return array{bundle_id:int,primary_sku_id:int,bundle_quantity:int,items:array,components:array}
     */
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
        if ($root->isVariable() && $root->variants()->exists()) {
            $primary = $primarySkuId ? Product::query()->active()
                ->where('parent_product_id', $root->id)->find($primarySkuId) : null;
            if (! $primary) {
                throw ValidationException::withMessages(['primary_sku_id' => 'Выберите действующее торговое предложение основного товара.']);
            }
        } else {
            if ($primarySkuId !== null && $primarySkuId !== (int) $root->id) {
                throw ValidationException::withMessages(['primary_sku_id' => 'Неверное торговое предложение основного товара.']);
            }
            $primary = $root;
        }
        $items = [['product_id' => (int) $primary->id, 'quantity' => $quantity]];
        $components = [['product_id' => (int) $primary->id, 'quantity_per_bundle' => 1, 'role' => 'primary']];
        // Lock links during checkout, blocking concurrent configuration changes.
        $parts = $root->sellableBundleComponents()->with('parentProduct')
            ->orderBy('sellable_bundle_components.sort_order')
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->get();
        if ($parts->isEmpty()) {
            throw ValidationException::withMessages(['bundle_id' => 'У комплекта не задан состав.']);
        }
        if ($parts->count() > 29) {
            throw ValidationException::withMessages(['bundle_id' => 'В комплекте допускается не более 29 дополнительных SKU.']);
        }
        foreach ($parts as $sku) {
            if (! $this->componentIsSellable($sku, $root)) {
                throw ValidationException::withMessages(['bundle_id' => 'Компонент комплекта неактивен или не имеет конкретного SKU.']);
            }
            $units = (int) $sku->pivot->quantity;
            if ($units < 1 || $units > 1000 || $units * $quantity > 1000) {
                throw ValidationException::withMessages(['quantity' => 'Количество компонентов превышает допустимый предел.']);
            }
            $components[] = ['product_id' => (int) $sku->id, 'quantity_per_bundle' => $units, 'role' => 'component'];
            $items[] = ['product_id' => (int) $sku->id, 'quantity' => $units * $quantity];
        }
        return ['bundle_id' => (int) $root->id, 'primary_sku_id' => (int) $primary->id,
            'bundle_quantity' => $quantity, 'items' => $items, 'components' => $components];
    }

    public function quote(int $bundleId, ?int $primarySkuId, int $quantity = 1, ?ShippingLocation $region = null): array
    {
        $composition = $this->composition($bundleId, $primarySkuId, $quantity);
        $ids = array_column($composition['items'], 'product_id');
        $skus = Product::with('parentProduct')->whereIn('id', $ids)->get()->keyBy('id');
        $pricing = app(PricingEngine::class);
        $subtotal = 0;
        $rows = [];
        foreach ($composition['items'] as $item) {
            $sku = $skus[$item['product_id']];
            $unit = $pricing->explain($sku, $region)['price'];
            $amount = round((float) $unit * $item['quantity'], 2);
            $subtotal += $amount;
            $rows[] = [
                'product_id' => $sku->id, 'sku' => $sku->sku, 'name' => $sku->name,
                'quantity' => $item['quantity'], 'unit_price' => (float) $unit, 'total' => $amount,
            ];
        }
        return [...$composition, 'subtotal' => round($subtotal, 2), 'priced_components' => $rows];
    }

    /** Fail closed if composition changes between preview and checkout submission. */
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
