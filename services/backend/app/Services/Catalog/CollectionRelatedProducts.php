<?php

namespace App\Services\Catalog;

use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use Illuminate\Support\Collection;

/**
 * Opt-in, collection-wide related products (editorial series/lineup).
 * Does not modify sellable bundle composition, orders or manual relations.
 */
class CollectionRelatedProducts
{
    public const MAX_PRODUCTS = 24;

    /**
     * @param array<string|int,mixed> $eagerLoad
     * @param array<int,int> $excludedProductIds
     * @return Collection<int,Product>
     */
    public function forProduct(Product $root, array $eagerLoad, array $excludedProductIds = []): Collection
    {
        $collections = ProductCollection::query()
            ->active()
            ->where('is_auto', false)
            ->whereNull('scope_type')
            ->where('recommend_collection_members', true)
            ->whereHas('products', fn ($query) => $query->where('products.id', $root->id))
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($collections->isEmpty()) {
            return collect();
        }

        $excluded = array_values(array_unique(array_map(
            'intval', array_merge([$root->id], $excludedProductIds)
        )));
        $results = collect();

        foreach ($collections as $collection) {
            $remaining = self::MAX_PRODUCTS - $results->count();
            if ($remaining <= 0) {
                break;
            }

            // One recommendation per parent card, not one per color variation.
            $members = $collection->products()
                ->whereNull('products.parent_product_id')
                ->whereNotIn('products.id', $excluded)
                ->active()
                ->with($eagerLoad)
                ->orderBy('product_product_collection.sort_order')
                ->orderBy('products.id')
                ->limit($remaining)
                ->get();

            foreach ($members as $member) {
                $results->push($member);
                $excluded[] = (int) $member->id; // avoid duplicates across series
            }
        }

        return $results;
    }
}
