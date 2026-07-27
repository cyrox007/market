<?php

namespace App\Http\Resources;

use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
                : $this->variants()->orderBy('id')->get();

            if ($variants->isNotEmpty()) {
                // Применяем правила для каждой вариации и находим минимальную цену
                $variantsWithPrices = $variants->map(function ($variant) use ($region, $regionRuleService) {
                    $variantPrice = $regionRuleService->getPriceForRegion($this->resource, $region, $variant);
                    return ['variant' => $variant, 'price' => $variantPrice];
                });
                $minPrice = $variantsWithPrices->min('price');
                $maxPrice = $variantsWithPrices->max('price');
                if ($minPrice !== null) {
                    $price = (float) $minPrice;
                }
                // Вариация с минимальной ценой — для подсветки цвета/размера в карточке
                $cheapestEntry = $variantsWithPrices->where('price', $minPrice)->first();
                if ($cheapestEntry) {
                    $v = $cheapestEntry['variant'];
                    if ($v->color) {
                        $defaultColor = [
                            'id' => null,
                            'name' => $v->color,
                            'slug' => \Str::slug($v->color),
                            'code' => $v->color_code ?? null,
                        ];
                    }
                    if ($v->length && $v->width) {
                        $sizeStr = "{$v->length}x{$v->width}";
                        $defaultSize = [
                            'id' => null,
                            'name' => "{$v->length} x {$v->width} см",
                            'slug' => \Str::slug($sizeStr),
                            'value' => $sizeStr,
                        ];
                    }
                }

                // Получаем срок доставки из правил (берем первый доступный)
                if ($region) {
                    $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($this->resource, $region);
                }
                // Получаем ID первого доступного варианта (in_stock = true и stock > 0)
                $firstAvailableVariant = $variants
                    ->filter(function ($variant) {
                        $stock = $variant->attributes['stock'] ?? $variant->stock ?? 0;
                        $backorder = $variant->attributes['backorder'] ?? $variant->backorder ?? false;
                        return ($stock > 0) || $backorder;
                    })
                    ->sortBy('id')
                    ->first();
                if (!$firstAvailableVariant && $variants->isNotEmpty()) {
                    $firstAvailableVariant = $variants->sortBy('id')->first();
                }
                if ($firstAvailableVariant) {
                    $firstAvailableVariantId = $firstAvailableVariant->id;
                }
            }
        }

        $isListView = (bool) $request->get('_list_view', false);

        // Для списка карточек не рассчитываем палитру цветов — эти данные не используются в ProductCard.
        $colors = [];
        if (!$isListView) {
            $colorValues = $this->getAvailableColors();
            $colors = $colorValues->take(10)->map(function ($value) {
                return [
                    'id' => $value->id ?? null,
                    'name' => $value->value ?? $value->name ?? null,
                    'slug' => $value->slug ?? \Str::slug($value->value ?? $value->name ?? ''),
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
        if (!$this->isVariable() && $region) {
            $price = $regionRuleService->getPriceForRegion($this->resource, $region);
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
        $stocks = [];
        if ($this->relationLoaded('warehouseStocks')) {
            $stocks = $this->warehouseStocks->map(function ($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name ?? 'Склад #' . $stock->warehouse_id,
                    'quantity' => (int) $stock->quantity,
                ];
            })->values()->toArray();
        }
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $isListView ? null : $this->sku,
            'price' => $price,
            'delivery_days' => $deliveryDays,
            'old_price' => $oldPrice,
            'discount_percent' => $this->discount_percent,
            // Для списков: image — основное изображение (оригинал), thumbnail — миниатюра,
            // дополнительные сжатые варианты: image_hd и image_fullhd
            'image' => $this->main_image_url,
            'image_hd' => $isListView ? null : $this->image_hd_url,
            'image_fullhd' => $isListView ? null : $this->image_fullhd_url,
            'thumbnail' => $this->thumbnail_url,
            'in_stock' => $resolvedStock > 0 || $this->backorder,
            'stock' => $resolvedStock,
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
                    'slug' => $this->taxons->first()->slug ?? \Str::slug($this->taxons->first()->name),
                ] : null
            ),
            'categories' => $this->when(
                $this->relationLoaded('taxons') && $this->taxons->isNotEmpty(),
                fn () => $this->taxons->map(fn ($taxon) => [
                    'id' => $taxon->id,
                    'name' => $taxon->name,
                    'slug' => $taxon->slug ?? \Str::slug($taxon->name),
                ])->values()
            ),
            //'excerpt' => $this->excerpt,
            'is_visible_in_region' => $isVisibleInRegion,
            'seo' => $isListView ? null : SeoApiTransformer::forModel($this->resource),
        ];
    }
}
