<?php
namespace App\Services\Product;

use App\Models\Product\Product;
use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\CartStockReservations;
use App\Services\Pricing\PrivateOfferAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vanilo\Cart\Facades\Cart;

/** A cart keeps virtual bundles as whole items; fulfillment always uses physical SKUs. */
class CartFulfillment
{
    public function composition(Product $product, int $quantity = 1): ?array
    {
        $root = $product->isVariant() ? $product->parentProduct : $product;
        return $root?->is_sellable_bundle
            ? app(SellableBundle::class)->composition($root->id, $product->isVariant() ? $product->id : null, $quantity)
            : null;
    }

    /** JSON object key order is not significant; component order, values and types remain strict. */
    private function sameSelection(mixed $saved, array $current): bool
    {
        if (! is_array($saved)) { return false; }
        $normalize = static function (array $items): array {
            return array_map(static function ($item) {
                if (is_array($item)) { ksort($item); }
                return $item;
            }, $items);
        };

        return $normalize($saved) === $normalize($current);
    }

    public function rememberSelection(object $item, Product $product): void
    {
        $composition = $this->composition($product);
        if ($composition === null) { return; }
        $configuration = $item->configuration ?? [];
        if (isset($configuration['bundle_selection']) && ! $this->sameSelection($configuration['bundle_selection'], $composition['items'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'Состав комплекта изменился. Удалите его из корзины и добавьте заново.']);
        }
        $item->update(['configuration' => [...$configuration, 'bundle_selection' => $composition['items']]]);
    }

    public function expand(iterable $items): Collection
    {
        $result = collect();
        foreach ($items as $item) {
            if (isset(($item->configuration ?? [])['bundle_composition'])) { $result->push($item); continue; }
            $product = $item->buyable ?? Product::findOrFail($item->product_id);
            $composition = isset($item->id) ? $this->composition($product, (int) $item->quantity) : null;
            if ($composition === null) { $result->push($item); continue; }
            $saved = ($item->configuration ?? [])['bundle_selection'] ?? null;
            if ($saved !== null && ! $this->sameSelection($saved, $this->composition($product)['items'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cart' => 'Состав комплекта изменился. Удалите его из корзины и добавьте заново.']);
            }
            foreach ($composition['items'] as $component) {
                $result->push((object) [
                    'product_id' => $component['product_id'], 'quantity' => $component['quantity'],
                    'buyable' => Product::findOrFail($component['product_id']),
                    'configuration' => ['bundle_composition' => $composition, 'bundle_cart_item_id' => $item->id],
                ]);
            }
        }
        return $result;
    }

    public function pricedItems(iterable $items, ?ShippingLocation $region): array
    {
        $result = [];
        $groups = $this->expand($items)->groupBy(fn ($item) => isset($item->configuration['bundle_composition'])
            ? 'bundle:'.$item->configuration['bundle_cart_item_id'] : 'single:'.spl_object_id($item));
        foreach ($groups as $group) {
            $composition = $group->first()->configuration['bundle_composition'] ?? null;
            $priced = app(PrivateOfferAccess::class)->pricedItems($group, $region);
            if ($composition !== null) {
                $priced = app(BundlePricing::class)->apply($composition, $priced);
                foreach ($priced as &$line) {
                    $line['pricing_snapshot']['bundle'] = [
                        'bundle_id' => $composition['bundle_id'], 'primary_sku_id' => $composition['primary_sku_id'],
                        'bundle_quantity' => $composition['bundle_quantity'],
                        'cart_item_id' => $group->first()->configuration['bundle_cart_item_id'],
                    ];
                }
                unset($line);
            }
            array_push($result, ...$priced);
        }
        return $result;
    }

    public function snapshot(iterable $items, array $priced): ?array
    {
        $bundles = [];
        foreach ($this->expand($items)->filter(fn ($item) => isset($item->configuration['bundle_composition']))
            ->groupBy(fn ($item) => $item->configuration['bundle_cart_item_id']) as $id => $group) {
            $composition = $group->first()->configuration['bundle_composition'];
            $lines = collect($priced)->filter(fn ($line) => ($line['pricing_snapshot']['bundle']['cart_item_id'] ?? null) === $id);
            $bundles[] = [...$composition, 'cart_item_id' => $id,
                'subtotal' => round($lines->sum(fn ($line) => $line['price'] * $line['quantity']), 2),
                'price_allocations' => $lines->map(fn ($line) => [
                    'product_id' => $line['product_id'], 'quantity' => $line['quantity'], 'unit_price' => $line['price'],
                ])->values()->all()];
        }
        return $bundles === [] ? null : ['version' => 2, 'bundles' => $bundles];
    }

    public function reserveCart(): void
    {
        if (! Cart::model()) { return; }
        DB::table('carts')->where('id', Cart::model()->id)->lockForUpdate()->first();
        $items = $this->expand(Cart::model()->items()->get());
        $reservations = app(CartStockReservations::class);
        $quantities = $items->groupBy('product_id')->map(fn ($rows) => (int) $rows->sum('quantity'));
        foreach ($quantities->sortKeys() as $id => $quantity) {
            $reservations->reserve(Product::findOrFail($id), $quantity);
        }
        DB::table('cart_stock_reservations')->where('owner_hash', $reservations->owner())
            ->whereNotIn('product_id', $quantities->keys())->delete();
    }
}
