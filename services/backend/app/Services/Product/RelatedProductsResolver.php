<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use App\Services\Catalog\CollectionRelatedProducts;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the existing "Related products" section.
 * Manual links, enabled furniture series and legacy fallback all feed one list.
 * None of these editorial relations changes a sellable bundle's composition.
 */
class RelatedProductsResolver
{
    public const MAX_VISIBLE = 24;

    /**
     * @param array<int|string, mixed> $eagerLoad
     * @return Collection<int, array{product: Product, source: string, series_name: ?string}>
     */
    public function forProduct(Product $product, array $eagerLoad = []): Collection
    {
        $root = $product->isVariant() ? $product->parentProduct : $product;
        if (! $root instanceof Product || $root->related_products_mode === 'off') {
            return collect();
        }

        $root->loadMissing('taxons');
        $mode = $root->related_products_mode;

        $result = collect();
        if ($mode !== 'automatic') {
            $manual = $root->relatedProducts()
                ->whereNull('parent_product_id')
                ->active()
                ->with($eagerLoad)
                ->limit(4)
                ->get();

            foreach ($manual as $item) {
                $result->push(['product' => $item, 'source' => 'manual', 'series_name' => null]);
            }
        }

        if ($mode === 'manual') {
            return $result;
        }

        $excludedIds = array_merge(
            [(int) $root->id],
            $root->bundleProducts()->pluck('products.id')->map(fn ($id) => (int) $id)->all(),
            $result->pluck('product.id')->map(fn ($id) => (int) $id)->all()
        );
        $series = app(CollectionRelatedProducts::class)
            ->forProductWithSources($root, $eagerLoad, $excludedIds);

        // The series ENHANCES existing hand-picked related products. With no
        // enabled series, the previous manual-first behavior remains unchanged.
        foreach ($series as $entry) {
            if ($result->count() >= self::MAX_VISIBLE) {
                break;
            }
            $result->push($entry);
        }
        if ($result->isNotEmpty()) {
            return $result->values();
        }

        $categoryIds = $root->taxons->pluck('id');
        if ($categoryIds->isNotEmpty()) {
            $categoryRelated = Product::whereNotIn('id', $excludedIds)
                ->whereNull('parent_product_id')
                ->whereHas('taxons', fn ($query) => $query->whereIn('taxons.id', $categoryIds))
                ->active()
                ->with($eagerLoad)
                ->limit(4)
                ->get();
            if ($categoryRelated->isNotEmpty()) {
                return $categoryRelated->map(fn (Product $item) => [
                    'product' => $item, 'source' => 'category', 'series_name' => null,
                ])->values();
            }
        }

        return Product::whereNotIn('id', $excludedIds)
            ->whereNull('parent_product_id')
            ->active()
            ->with($eagerLoad)
            ->limit(4)
            ->get()
            ->map(fn (Product $item) => [
                'product' => $item, 'source' => 'fallback', 'series_name' => null,
            ])->values();
    }
}
