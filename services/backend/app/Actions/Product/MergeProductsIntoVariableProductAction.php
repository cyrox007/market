<?php

namespace App\Actions\Product;

use App\Actions\Product\Data\MergedProductDraft;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Объединяет простые товары в вариативный: создаёт общую карточку,
 * а все выбранные товары становятся её торговыми предложениями.
 */
class MergeProductsIntoVariableProductAction
{
    public const MAX_PRODUCTS = 20;

    /** Префикс служебного артикула общей карточки, отличимый от артикулов 1С. */
    public const PARENT_SKU_PREFIX = 'VAR-';

    public function __construct(
        protected SyncVariantVariationAttributesAction $syncVariantVariationAttributesAction,
        protected GetVariationAttributesForProductAction $getVariationAttributesForProductAction,
        protected BuildMergeVariantFormDataAction $buildMergeVariantFormDataAction,
        protected BuildMergedProductDraftAction $buildMergedProductDraftAction,
    ) {
    }

    public function execute(MergeProductsIntoVariableProductData $data): Product
    {
        Log::info('[MergeProductsIntoVariableProduct] start', [
            'product_ids' => $data->productIds,
        ]);

        $products = $this->loadAndValidateProducts($data);
        $draft = $this->buildMergedProductDraftAction->execute($products);
        $labels = $this->resolveLabels($data, $products, $draft);

        $parent = DB::transaction(function () use ($data, $products, $draft, $labels) {
            $parent = $this->createParent($data, $products, $draft);

            $variationAttributeIds = $this->resolveVariationAttributeIdsForParent($parent);
            $parent->variationAttributeSelection()->sync($variationAttributeIds);

            foreach ($products as $variant) {
                $variant->updateQuietly([
                    'parent_product_id' => $parent->id,
                    'is_variable' => false,
                ]);

                $formData = $this->buildMergeVariantFormDataAction->execute($parent, $variant, $labels[$variant->id]);

                $this->syncVariantVariationAttributesAction->execute(
                    $variant->fresh(),
                    $formData,
                    $parent,
                );

                Log::debug('[MergeProductsIntoVariableProduct] linked variant', [
                    'variant_id' => $variant->id,
                    'parent_id' => $parent->id,
                    'external_id' => $variant->external_id,
                    'sku' => $variant->sku,
                ]);
            }

            Product::syncParentPriceFromVariants($parent->fresh());
            $parent->flushCache();
            Product::flushAllProductCaches();

            foreach ($products as $variant) {
                $variant->fresh()->flushCache();
            }

            Log::info('[MergeProductsIntoVariableProduct] completed', [
                'parent_id' => $parent->id,
                'variants_count' => $products->count(),
                'warnings' => $draft->warnings,
            ]);

            return $parent;
        });

        // Копия файла — только после успешной фиксации объединения
        $this->copyMainImage($parent, $products, $draft);

        return $parent->fresh(['variants']);
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    protected function copyMainImage(Product $parent, Collection $products, MergedProductDraft $draft): void
    {
        if ($draft->imageSourceProductId === null) {
            return;
        }

        $products->firstWhere('id', $draft->imageSourceProductId)
            ?->getFirstMedia('images')
            ?->copy($parent, 'images');
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    protected function createParent(
        MergeProductsIntoVariableProductData $data,
        Collection $products,
        MergedProductDraft $draft,
    ): Product {
        $name = trim((string) ($data->name ?? ''));

        $parent = new Product;
        $parent->name = $name !== '' ? $name : $draft->name;
        $parent->sku = self::PARENT_SKU_PREFIX . 'new';
        $parent->description = $draft->description;
        $parent->manufacturer_id = $draft->manufacturerId;
        $parent->state = Product::ACTIVE;
        $parent->is_variable = true;
        $parent->price = (float) $products->min('price');
        $parent->stock = 0;
        $parent->backorder = false;
        $parent->units_sold = 0;
        // ЧПУ генерирует Sluggable в событии сохранения — нужны события модели
        $parent->save();

        $parent->forceFill(['sku' => self::PARENT_SKU_PREFIX . $parent->id])->saveQuietly();

        if ($draft->taxonIds !== []) {
            $parent->taxons()->sync($draft->taxonIds);
        }

        foreach ($draft->commonAttributes as $attributeId => $value) {
            $parent->attributes()->attach($attributeId, $value);
        }

        Log::info('[MergeProductsIntoVariableProduct] parent created', [
            'parent_id' => $parent->id,
            'taxon_ids' => $draft->taxonIds,
            'common_attribute_ids' => array_keys($draft->commonAttributes),
            'image_source_product_id' => $draft->imageSourceProductId,
        ]);

        return $parent;
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

        return $attributes->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
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

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['taxons', 'variants', 'warehouseStocks', 'attributes', 'media'])
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

        return collect($ids)->map(fn (int $id) => $products[$id])->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    protected function resolveLabels(
        MergeProductsIntoVariableProductData $data,
        Collection $products,
        MergedProductDraft $draft,
    ): array {
        Attribute::ensureVariantAttribute();

        $labels = [];
        $seen = [];

        foreach ($products as $product) {
            $override = trim((string) ($data->variantLabelOverrides[$product->id] ?? ''));
            $label = $override !== '' ? $override : ($draft->variantLabels[$product->id] ?? '');
            $label = $this->buildMergeVariantFormDataAction->resolveOfferLabel($product, $label);
            $normalized = mb_strtolower(trim($label));

            if ($normalized === '') {
                throw new InvalidArgumentException(
                    "Не удалось определить название вариации для товара «{$product->name}».",
                );
            }

            if (isset($seen[$normalized])) {
                throw new InvalidArgumentException(
                    'Названия вариаций (торговых предложений) должны быть уникальными. Дубликат: «' . $label . '».',
                );
            }

            $seen[$normalized] = true;
            $labels[$product->id] = $label;
        }

        return $labels;
    }
}
