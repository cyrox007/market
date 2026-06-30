<?php

namespace App\Actions\Product;

use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MergeProductsIntoVariableProductAction
{
    public const MAX_PRODUCTS = 20;

    public function __construct(
        protected SyncVariantVariationAttributesAction $syncVariantVariationAttributesAction,
        protected GetVariationAttributesForProductAction $getVariationAttributesForProductAction,
        protected BuildMergeVariantFormDataAction $buildMergeVariantFormDataAction,
    ) {
    }

    public function execute(MergeProductsIntoVariableProductData $data): Product
    {
        Log::info('[MergeProductsIntoVariableProduct] start', [
            'parent_id' => $data->parentId,
            'product_ids' => $data->productIds,
        ]);

        $products = $this->loadAndValidateProducts($data);

        return DB::transaction(function () use ($data, $products) {
            /** @var Product $parent */
            $parent = $products->firstWhere('id', $data->parentId);

            $variationAttributeIds = $this->resolveVariationAttributeIdsForParent($parent);

            Log::debug('[MergeProductsIntoVariableProduct] auto variation attributes', [
                'parent_id' => $parent->id,
                'attribute_ids' => $variationAttributeIds,
            ]);

            $this->syncParentVariationAttributeSelection($parent, $variationAttributeIds);

            $parentUpdates = [
                'is_variable' => true,
                'parent_product_id' => null,
            ];

            $parentName = trim((string) ($data->parentName ?? ''));
            if ($parentName !== '') {
                $parentUpdates['name'] = $parentName;
            }

            $parent->updateQuietly($parentUpdates);

            $variantProducts = $products->where('id', '!=', $parent->id);

            foreach ($variantProducts as $variant) {
                $variant->updateQuietly([
                    'parent_product_id' => $parent->id,
                    'is_variable' => false,
                ]);

                Log::debug('[MergeProductsIntoVariableProduct] linked variant', [
                    'variant_id' => $variant->id,
                    'parent_id' => $parent->id,
                    'external_id' => $variant->external_id,
                    'sku' => $variant->sku,
                ]);

                $labelOverride = $data->variantLabelOverrides[$variant->id] ?? null;
                $formData = $this->buildMergeVariantFormDataAction->execute($parent, $variant, $labelOverride);

                $this->syncVariantVariationAttributesAction->execute(
                    $variant->fresh(),
                    $formData,
                    $parent,
                );
            }

            if ($data->mergeCategories) {
                $this->mergeTaxons($parent, $products);
            }

            Product::syncParentPriceFromVariants($parent->fresh());
            $parent->flushCache();
            Product::flushAllProductCaches();

            foreach ($variantProducts as $variant) {
                $variant->fresh()->flushCache();
            }

            Log::info('[MergeProductsIntoVariableProduct] completed', [
                'parent_id' => $parent->id,
                'variants_count' => $variantProducts->count(),
            ]);

            return $parent->fresh(['variants']);
        });
    }

    /**
     * @return list<int>
     */
    protected function resolveVariationAttributeIdsForParent(Product $parent): array
    {
        $attributes = $this->getVariationAttributesForProductAction->execute($parent);

        if ($attributes->isEmpty()) {
            return [(int) Attribute::ensureVariantAttribute()->id];
        }

        return $attributes->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return Collection<int, Product>
     */
    protected function loadAndValidateProducts(MergeProductsIntoVariableProductData $data): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $data->productIds)));

        if (count($ids) < 2) {
            throw new InvalidArgumentException('Выберите минимум 2 товара для объединения.');
        }

        if (count($ids) > self::MAX_PRODUCTS) {
            throw new InvalidArgumentException('За один раз можно объединить не более ' . self::MAX_PRODUCTS . ' товаров.');
        }

        if (! in_array($data->parentId, $ids, true)) {
            throw new InvalidArgumentException('Родительский товар должен быть среди выбранных.');
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['taxons', 'variants', 'warehouseStocks', 'attributes'])
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($ids)) {
            throw new InvalidArgumentException('Не все выбранные товары найдены.');
        }

        foreach ($products as $product) {
            if ($product->isVariant()) {
                throw new InvalidArgumentException(
                    "Товар «{$product->name}» уже является торговым предложением и не может быть объединён.",
                );
            }

            if ($product->is_variable && $product->variants->isNotEmpty()) {
                throw new InvalidArgumentException(
                    "У товара «{$product->name}» уже есть торговые предложения.",
                );
            }
        }

        $externalIds = $products
            ->pluck('external_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id);

        if ($externalIds->count() !== $externalIds->unique()->count()) {
            throw new InvalidArgumentException('Среди выбранных товаров есть дубликаты external_id 1С.');
        }

        $this->validateUniqueOfferSignatures($data, $products);

        return $products->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    protected function validateUniqueOfferSignatures(
        MergeProductsIntoVariableProductData $data,
        Collection $products,
    ): void {
        $parent = $products->firstWhere('id', $data->parentId);
        if (! $parent) {
            return;
        }

        Attribute::ensureVariantAttribute();

        $labels = [];

        foreach ($products->where('id', '!=', $parent->id) as $variant) {
            $label = $this->buildMergeVariantFormDataAction->resolveOfferLabel(
                $variant,
                $data->variantLabelOverrides[$variant->id] ?? null,
            );
            $normalized = mb_strtolower(trim($label));

            if ($normalized === '') {
                throw new InvalidArgumentException(
                    "Не удалось определить название вариации для товара «{$variant->name}».",
                );
            }

            if (isset($labels[$normalized])) {
                throw new InvalidArgumentException(
                    'Названия вариаций (торговых предложений) должны быть уникальными. Дубликат: «' . $label . '».',
                );
            }

            $labels[$normalized] = $variant->id;
        }
    }

    /**
     * @param  list<int>  $variationAttributeIds
     */
    protected function syncParentVariationAttributeSelection(Product $parent, array $variationAttributeIds): void
    {
        if ($variationAttributeIds === []) {
            return;
        }

        $parent->variationAttributeSelection()->sync(
            array_values(array_unique(array_map('intval', $variationAttributeIds))),
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    protected function mergeTaxons(Product $parent, Collection $products): void
    {
        $taxonIds = $products
            ->flatMap(fn (Product $p) => $p->taxons->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if ($taxonIds !== []) {
            $parent->taxons()->syncWithoutDetaching($taxonIds);
        }
    }
}
