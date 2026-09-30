<?php

namespace App\Actions\Product;

use App\Models\Product\Attribute;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Привязывает существующие самостоятельные товары к готовой общей карточке как торговые предложения.
 * Карточка не пересобирается; добавляются только категории привязываемых товаров.
 */
class AttachProductsToVariableProductAction
{
    public function __construct(
        protected SyncVariantVariationAttributesAction $syncVariantVariationAttributesAction,
        protected BuildMergeVariantFormDataAction $buildMergeVariantFormDataAction,
        protected BuildMergedProductDraftAction $buildMergedProductDraftAction,
    ) {
    }

    /**
     * @param  array<int, string>  $labels  product_id => название вариации; пусто = авто
     * @return array{added_category_names: list<string>}
     */
    public function execute(Product $parent, array $labels): array
    {
        if ($parent->isVariant() || ! $parent->isVariable()) {
            throw new InvalidArgumentException('Привязывать товары можно только к вариативной общей карточке.');
        }

        $products = $this->loadAndValidateProducts($parent, array_keys($labels));
        $resolvedLabels = $this->resolveLabels($parent, $products, $labels);
        $addedCategoryNames = $this->newCategoryNames($parent, $products);

        DB::transaction(function () use ($parent, $products, $resolvedLabels): void {
            $taxonIds = $products->flatMap(fn (Product $product) => $product->taxons->pluck('id'))->unique()->values()->all();
            if ($taxonIds !== []) {
                $parent->taxons()->syncWithoutDetaching($taxonIds);
            }

            foreach ($products as $product) {
                $product->updateQuietly([
                    'parent_product_id' => $parent->id,
                    'is_variable' => false,
                ]);

                $formData = $this->buildMergeVariantFormDataAction->execute($parent, $product, $resolvedLabels[$product->id]);
                $this->syncVariantVariationAttributesAction->execute($product->fresh(), $formData, $parent);
            }

            Product::syncParentPriceFromVariants($parent->fresh());
        });

        $parent->fresh()->flushCache();
        Product::flushAllProductCaches();
        $products->each(fn (Product $product) => $product->fresh()->flushCache());

        Log::info('[AttachProductsToVariableProduct] attached', [
            'parent_id' => $parent->id,
            'product_ids' => $products->pluck('id')->all(),
            'added_categories' => $addedCategoryNames,
        ]);

        return ['added_category_names' => $addedCategoryNames];
    }

    /**
     * Категории товаров, которых ещё нет у карточки.
     *
     * @param  Collection<int, Product>  $products
     * @return list<string>
     */
    public function newCategoryNames(Product $parent, Collection $products): array
    {
        $parentTaxonIds = $parent->taxons()->pluck('taxons.id')->all();

        return $products
            ->flatMap(fn (Product $product) => $product->taxons)
            ->reject(fn ($taxon) => in_array($taxon->id, $parentTaxonIds, true))
            ->unique('id')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    protected function loadAndValidateProducts(Product $parent, array $productIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));

        if ($ids === []) {
            throw new InvalidArgumentException('Выберите хотя бы один товар.');
        }

        $products = Product::query()->whereIn('id', $ids)->with(['taxons', 'variants', 'attributes'])->get()->keyBy('id');

        if ($products->count() !== count($ids)) {
            throw new InvalidArgumentException('Не все выбранные товары найдены.');
        }

        foreach ($products as $product) {
            if ((int) $product->id === (int) $parent->id) {
                throw new InvalidArgumentException('Нельзя привязать карточку к самой себе.');
            }

            if ($product->isVariant()) {
                throw new InvalidArgumentException("Товар «{$product->name}» уже является торговым предложением.");
            }

            if ($product->variants->isNotEmpty()) {
                throw new InvalidArgumentException("У товара «{$product->name}» есть свои торговые предложения.");
            }
        }

        return collect($ids)->map(fn (int $id) => $products[$id])->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    protected function resolveLabels(Product $parent, Collection $products, array $labels): array
    {
        $variantAttributeId = (int) Attribute::ensureVariantAttribute()->id;

        $seen = DB::table('product_variant_attributes')
            ->whereIn('product_id', $parent->variants()->pluck('id'))
            ->where('attribute_id', $variantAttributeId)
            ->pluck('custom_value')
            ->filter()
            ->mapWithKeys(fn ($label) => [mb_strtolower(trim((string) $label)) => true])
            ->all();

        $resolved = [];

        foreach ($products as $product) {
            $label = trim((string) ($labels[$product->id] ?? ''));
            if ($label === '') {
                $label = $this->buildMergedProductDraftAction->suggestLabelForParent($parent, $product);
            }

            $normalized = mb_strtolower($label);
            if ($normalized === '') {
                throw new InvalidArgumentException("Не удалось определить название вариации для товара «{$product->name}».");
            }

            if (isset($seen[$normalized])) {
                throw new InvalidArgumentException("В группе уже есть вариация «{$label}».");
            }

            $seen[$normalized] = true;
            $resolved[$product->id] = $label;
        }

        return $resolved;
    }
}
