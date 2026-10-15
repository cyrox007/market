<?php

namespace App\Http\Resources;

use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Product\ProductVariationAttributeService;
use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Регион: используем предзагруженный объект из request (избегаем N+1 find на каждый товар в списке)
        $region = $request->get('_region');
        if (!$region && $regionId = $request->get('_region_id')) {
            $region = ShippingLocation::find($regionId);
        }

        if (!$this->relationLoaded('warehouseStocks')) {
            $this->load('warehouseStocks.warehouse');
        }

        $regionRuleService = app(ProductRegionRuleService::class);
        $variationAttributeService = app(ProductVariationAttributeService::class);
        $warehouseStockResolver = app(WarehouseStockResolver::class);

        // Для вариативных товаров получаем минимальную цену из вариаций
        $price = (float) $this->price;
        $oldPrice = $this->original_price ? (float) $this->original_price : null;
        $firstAvailableVariantId = null;
        $deliveryDays = null;
        $defaultColor = null;
        $defaultSize = null;

        if ($this->isVariable()) {
            // Загружаем варианты, если они не загружены
            $variants = $this->relationLoaded('variants')
                ? $this->variants
                : $this->variants()->active()->orderBy('id')->get();
            $variants = $variants->filter(fn ($variant) => $variant->getRawOriginal('state') === 'active');
            if ($request->attributes->has('matching_sku_ids')) {
                $variants = $variants->whereIn('id', $request->attributes->get('matching_sku_ids'));
            }

            if ($variants->isNotEmpty()) {
                // Применяем правила для каждой вариации и находим минимальную цену
                $variantsWithPrices = $variants->map(function ($variant) use ($region, $regionRuleService) {
                    $variantPrice = $regionRuleService->getPriceForRegion($this->resource, $region, $variant);
                    return ['variant' => $variant, 'price' => $variantPrice];
                });
                $minPrice = $variantsWithPrices->min('price');
                if ($minPrice !== null) {
                    $price = (float) $minPrice;
                }

                // Цвет и коммерческий размер берём только из канонических атрибутов вариации.
                // length/width/height остаются физическими габаритами и не участвуют в выборе размера.
                $cheapestEntry = $variantsWithPrices->where('price', $minPrice)->first();
                if ($cheapestEntry) {
                    $oldPrice = app(\App\Services\Pricing\PricingEngine::class)->oldPrice($cheapestEntry['variant'], $region, (float) $minPrice);
                    $selected = $variationAttributeService->selectedForVariant($cheapestEntry['variant']);
                    $defaultColor = $selected['color'];
                    $defaultSize = $selected['size'];
                }

                // Получаем срок доставки из правил (берем первый доступный)
                if ($region) {
                    $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($this->resource, $region);
                }
                // Получаем ID первого доступного варианта (in_stock = true и stock > 0)
                $firstAvailableVariant = $variants
                    ->filter(fn ($variant) => ($warehouseStockResolver->resolveForProduct($variant, $region) ?? 0) > 0)
                    ->sortBy('id')
                    ->first();
                if ($firstAvailableVariant) {
                    $firstAvailableVariantId = $firstAvailableVariant->id;
                }
            }
        }

        $isListView = (bool) $request->get('_list_view', false);

        // Для списка карточек не рассчитываем палитру цветов — эти данные не используются в ProductCard.
        $colors = [];
        if (!$isListView) {
            $colorValues = $variationAttributeService->colors($this->resource);
            $colors = $colorValues->take(10)->map(function ($value) {
                return [
                    'id' => $value->id ?? null,
                    'name' => $value->value ?? $value->name ?? null,
                    'slug' => $value->slug ?? Str::slug($value->value ?? $value->name ?? ''),
                    'code' => $value->color_code ?? null,
                ];
            })->values()->toArray();
        }

        // В режиме списка не грузим и не сериализуем полные характеристики — снижает размер ответа и нагрузку
        $specifications = [];
        $specificationNames = [];
        if (!$isListView) {
            $attributes = collect();
            if ($this->relationLoaded('attributeValues')) {
                $attributes = $this->attributeValues;
            } else {
                $attributes = $this->attributeValues()->with('attribute')->get();
            }
            foreach ($attributes as $attrValue) {
                $attr = $attrValue->attribute;
                if ($attr) {
                    $specifications[$attr->slug] = $attrValue->value;
                    $specificationNames[$attr->slug] = $attr->name;
                }
            }
        }

        // Применяем правила для невариативных товаров
        if (!$this->isVariable()) {
            $price = $regionRuleService->getPriceForRegion($this->resource, $region);
            $oldPrice = app(\App\Services\Pricing\PricingEngine::class)->oldPrice($this->resource, $region, $price);
            $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($this->resource, $region);
        }

        // Проверяем видимость товара в регионе
        $isVisibleInRegion = true;
        if ($region) {
            $isVisibleInRegion = $regionRuleService->isVisibleInRegion($this->resource, $region);
        }

        // Физические характеристики — в списке не включаем для уменьшения payload
        if (!$isListView && ($this->length || $this->width || $this->height || $this->weight)) {
            $specifications['dimensions'] = ($this->length ?? '-') . ' x ' . ($this->width ?? '-') . ' x ' . ($this->height ?? '-') . ' см';
            $specificationNames['dimensions'] = 'Размеры (ДxШxВ)';
            if ($this->weight) {
                $specifications['weight'] = $this->weight . ' кг';
                $specificationNames['weight'] = 'Вес';
            }
        }

        $resolvedStock = $warehouseStockResolver->resolveForProduct($this->resource, $region);
        $stocks = app(\App\Services\Inventory\WarehousePriorityResolver::class)->resolve($this->resource, $region)['stocks'];
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $isListView ? null : $this->sku,
            'price' => $price,
            'delivery_days' => $deliveryDays,
            'delivery_days_source' => 'legacy_region_rule',
            'delivery_quote_endpoint' => '/api/v1/shipping/quote',
            'old_price' => $oldPrice,
            'discount_percent' => $oldPrice !== null && $oldPrice > $price ? (int) round((1 - $price / $oldPrice) * 100) : null,
            // Для списков: image — основное изображение (оригинал), thumbnail — миниатюра,
            // дополнительные сжатые варианты: image_hd и image_fullhd
            'image' => $this->main_image_url,
            'image_hd' => $isListView ? null : $this->image_hd_url,
            'image_fullhd' => $isListView ? null : $this->image_fullhd_url,
            'thumbnail' => $this->thumbnail_url,
            'in_stock' => $resolvedStock > 0,
            'available_to_order' => $warehouseStockResolver->availableToOrder($this->resource, $region),
            'stock_scope' => $region || ($request->attributes->get('warehouse_destination')['specified'] ?? false) ? 'destination' : 'global',
            'stock' => $resolvedStock,
            'stock_known' => $resolvedStock !== null,
            'nearest_distance_km' => $this->getAttributes()['nearest_distance_km'] ?? null,
            'distance_type' => 'straight_line',
            'stocks' => $stocks,
            'rating' => $this->rating ?? 0,
            'reviews_count' => $this->reviews_count ?? 0,
            'is_variable' => $this->isVariable(),
            'is_variant' => $this->isVariant(),
            'first_available_variant_id' => $firstAvailableVariantId,
            'default_color' => $isListView ? null : $defaultColor,
            'default_size' => $isListView ? null : $defaultSize,
            'colors' => $colors,
            'specifications' => $specifications,
            'specification_names' => $specificationNames,
            'backorder' => (bool) $this->backorder,
            'category' => $this->when(
                $this->relationLoaded('taxons') && $this->taxons->isNotEmpty(),
                fn() => $this->taxons->first() ? [
                    'id' => $this->taxons->first()->id,
                    'name' => $this->taxons->first()->name,
                    'slug' => $this->taxons->first()->slug ?? Str::slug($this->taxons->first()->name),
                ] : null
            ),
            'categories' => $this->when(
                $this->relationLoaded('taxons') && $this->taxons->isNotEmpty(),
                fn () => $this->taxons->map(fn ($taxon) => [
                    'id' => $taxon->id,
                    'name' => $taxon->name,
                    'slug' => $taxon->slug ?? Str::slug($taxon->name),
                ])->values()
            ),
            //'excerpt' => $this->excerpt,
            'is_visible_in_region' => $isVisibleInRegion,
            'seo' => $isListView ? null : SeoApiTransformer::forModel($this->resource),
        ];
    }
}
