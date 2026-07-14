<?php

namespace App\Http\Resources;

use App\Helpers\StockCategoryHelper;
use App\Models\Product\ProductDeliveryBlock;
use App\Models\Product\ProductFeatureBlock;
use App\Models\Shipping\ShippingLocation;
use App\Services\Inventory\WarehouseStockResolver;
use App\Services\Product\ProductRegionRuleService;
use App\Services\Seo\SeoApiTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Получаем регион из request (если передан)
        $region = null;
        $regionId = $request->get('_region_id');
        if ($regionId) {
            $region = ShippingLocation::find($regionId);
            if (!$region) {
                Log::warning('ProductDetailResource::toArray - Регион не найден по ID', [
                    'region_id' => $regionId,
                    'product_id' => $this->id,
                ]);
            }
        } else {
            Log::debug('ProductDetailResource::toArray - _region_id не передан в request', [
                'product_id' => $this->id,
                'request_keys' => array_keys($request->all()),
            ]);
        }

        $regionRuleService = app(ProductRegionRuleService::class);
        $warehouseStockResolver = app(WarehouseStockResolver::class);

        $isVariant = $this->isVariant();
        $isVariable = $this->isVariable();

        // ВАЖНО: Для вариативных товаров загружаем вариации перед использованием accessors
        if ($isVariable && !$isVariant) {
            if (!$this->relationLoaded('variants')) {
                $this->load([
                    'variants' => function ($query) {
                        $query->active()->with(['attributes']);
                    },
                ]);
            }
        }

        // ВАЖНО: Если это вариация, загружаем родительский продукт для применения правил корзины
        $parentProduct = null;
        if ($isVariant) {
            // Отладочное логирование для проверки вариации
            Log::debug('ProductDetailResource: Определена вариация', [
                'variant_id' => $this->id,
                'variant_sku' => $this->sku,
                'parent_product_id' => $this->parent_product_id,
                'has_parent_relation' => $this->relationLoaded('parentProduct'),
            ]);

            // Загружаем родительский товар, если он еще не загружен
            if (!$this->relationLoaded('parentProduct') && $this->parent_product_id) {
                $this->load([
                    'parentProduct' => function ($query) {
                        // ВАЖНО: Проверяем, что родительский товар активен
                        $query->active();
                    }
                ]);
            }
            $parentProduct = $this->parentProduct;

            // ВАЖНО: Если родительский товар неактивен, вариация не должна отображаться
            $parentIsActive = false;
            if ($parentProduct) {
                $state = $parentProduct->state;
                $parentIsActive = (string) $state === \App\Models\Product\Product::ACTIVE;
            }

            if (!$parentProduct || !$parentIsActive) {
                Log::warning('ProductDetailResource: Родительский товар неактивен или не найден', [
                    'variant_id' => $this->id,
                    'parent_product_id' => $this->parent_product_id,
                    'has_parent' => $parentProduct !== null,
                ]);
                return [];
            }

            Log::debug('ProductDetailResource: Родительский товар загружен', [
                'variant_id' => $this->id,
                'parent_product_id' => $parentProduct->id,
                'parent_product_sku' => $parentProduct->sku,
            ]);
        }

        // Источник данных для вариаций на карточке: родитель (опции, категория)
        $sourceProduct = $isVariant && $parentProduct ? $parentProduct : $this->resource;

        $this->resource->loadMissing(['attributes']);
        $specifications = $this->resource->buildSpecificationsForApi();

        if ($specifications === [] && $isVariant && $parentProduct) {
            $parentProduct->loadMissing(['attributes']);
            $specifications = $parentProduct->buildSpecificationsForApi();
        } elseif ($specifications === [] && ! $isVariant) {
            $attributes = $sourceProduct->relationLoaded('attributeValues')
                ? $sourceProduct->attributeValues
                : $sourceProduct->attributeValues()->with('attribute')->get();

            foreach ($attributes as $attrValue) {
                $attr = $attrValue->attribute;
                if ($attr) {
                    $specifications[] = [
                        'name' => $attr->name,
                        'value' => $attrValue->value,
                        'slug' => $attr->slug,
                    ];
                }
            }

            if ($this->length || $this->width || $this->height || $this->weight) {
                $specifications[] = ['name' => 'Размеры (ДxШxВ)', 'value' => ($this->length ?? '-') . ' x ' . ($this->width ?? '-') . ' x ' . ($this->height ?? '-') . ' см', 'slug' => 'dimensions'];
                if ($this->weight) {
                    $specifications[] = ['name' => 'Вес', 'value' => $this->weight . ' кг', 'slug' => 'weight'];
                }
            }
        }

        // variation_attributes: список атрибутов с is_use_in_variations и их доступными значениями
        $variationAttributes = [];
        $selectedVariation = [];

        if ($sourceProduct->isVariable()) {
            $attrSlugs = $sourceProduct->getVariationAttributeSlugs();
            foreach ($attrSlugs as $attrSlug) {
                $attr = \App\Models\Product\Attribute::where('slug', $attrSlug)->first();
                if (!$attr) {
                    continue;
                }

                // ГРУППИРУЕМ ЗНАЧЕНИЯ ИЗ ВСЕХ ВАРИАЦИЙ
                $allValues = collect();

                // Проходим по всем вариациям
                foreach ($this->variants as $variant) {
                    $variantAttrs = $variant->getVariationAttributesForApi();
                    foreach ($variantAttrs as $va) {
                        if ($va['attribute_slug'] === $attrSlug) {
                            $allValues->push([
                                'slug' => $va['value_slug'] ?? null,
                                'name' => $va['value_name'] ?? $va['value_slug'] ?? null,
                                'code' => $va['code'] ?? null,
                            ]);
                        }
                    }
                }

                // Убираем дубликаты по slug
                $uniqueValues = $allValues->unique('slug')->values()->toArray();

                if (empty($uniqueValues)) {
                    continue;
                }

                $variationAttributes[] = [
                    'attribute_slug' => $attr->slug,
                    'attribute_name' => $attr->name,
                    'type' => $attr->type ?? null,
                    'values' => $uniqueValues,
                    'is_multiple' => (bool) $attr->is_multiple, // 👈 ДОБАВЛЯЕМ ФЛАГ!
                ];
            }

            // selected_variation: для вариации — из её variantAttributeValues; для родителя — из самой дешёвой вариации
            if ($isVariant) {
                $selectedVariation = $this->resource->getVariationAttributesForApi();
            }
        }

        // ВАЖНО: Для вариативных товаров возвращаем все вариации
        // Фронтенд будет сравнивать цвет и размер с каждой вариацией
        $variants = [];
        $deliveryDays = null;
        $productPriceFromCheapestVariant = null;
        $skuFromCheapestVariant = null;

        if ($isVariable && !$isVariant) {
            // Загружаем все вариации (только активные), если они не загружены
            $allVariants = $this->relationLoaded('variants')
                ? $this->variants
                : $this->variants()->active()->orderBy('id')->get();

            $variants = $allVariants->map(function ($variant) use ($region, $regionRuleService, $warehouseStockResolver) {
                $variantPrice = (float) $variant->price;
                if ($region) {
                    $variantPrice = $regionRuleService->getPriceForRegion($this->resource, $region, $variant);
                }

                $variantSpecs = $variant->buildSpecificationsForApi();
                $variantStock = (int) round((float) ($warehouseStockResolver->resolveForProduct($variant, $region) ?? 0));
                
                $colorAttr = \App\Models\Product\Attribute::where('slug', 'color')->first();
                $colorValues = $colorAttr 
                    ? $variant->variantAttributes()->wherePivot('attribute_id', $colorAttr->id)->get()
                    : collect();

                $colors = [];
                foreach ($colorValues as $attr) {
                    $pivot = $attr->pivot;
                    $valueId = $pivot->attribute_value_id;
                    if ($valueId) {
                        $value = \App\Models\Product\AttributeValue::find($valueId);
                        if ($value) {
                            $colors[] = [
                                'id' => $value->id,
                                'value' => $value->value,
                                'slug' => $value->slug,
                                'color_code' => $value->color_code,
                            ];
                        }
                    }
                }
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variantPrice,
                    'old_price' => $variant->original_price ? (float) $variant->original_price : null,
                    'stock' => $variantStock,
                    'stock_label' => StockCategoryHelper::formatStockDisplay($variantStock),
                    'in_stock' => $variantStock > 0 || $variant->backorder,
                    'variation_attributes' => $variant->getVariationAttributesForApi(),
                    'images' => $variant->images_urls ?: ($variant->main_image_url ? [$variant->main_image_url] : []),
                    'description' => $variant->description,
                    //'excerpt' => $variant->excerpt,
                    'specifications' => $variantSpecs !== [] ? $variantSpecs : null,
                    'colors' => $colors,
                ];
            })->values()->toArray();

            if (!empty($variants)) {
                $cheapest = collect($variants)->sortBy('price')->first();
                if ($cheapest) {
                    $productPriceFromCheapestVariant = (float) $cheapest['price'];
                    $skuFromCheapestVariant = $cheapest['sku'] ?? '';
                    $selectedVariation = $cheapest['variation_attributes'] ?? [];
                }
            }

            // Получаем срок доставки из правил
            if ($region) {
                $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($this->resource, $region);
            }
        }

        // УПРОЩЕННАЯ ЛОГИКА: Применяем правила для цены товара
        // Для вариаций: передаем родительский товар и саму вариацию
        // Для обычных товаров: передаем сам товар
        // ПРЯМОЕ ОБРАЩЕНИЕ К АТРИБУТАМ МОДЕЛИ для гарантии получения правильного значения
        $basePrice = (float) ($this->attributes['price'] ?? $this->price ?? 0);
        $productPrice = $basePrice;

        if ($region) {
            if ($isVariant && $parentProduct) {
                // Для вариации: правила применяются к цене вариации с учетом родительского товара
                // ВАЖНО: Передаем $this->resource (саму вариацию) как третий параметр
                $productPrice = $regionRuleService->getPriceForRegion($parentProduct, $region, $this->resource);

                // Отладочный вывод для проверки применения правил
                Log::debug('ProductDetailResource: Применение правил для вариации', [
                    'variant_id' => $this->id,
                    'variant_sku' => $this->attributes['sku'] ?? $this->sku ?? '',
                    'variant_price_from_attributes' => $this->attributes['price'] ?? 'NOT_SET',
                    'variant_price_from_accessor' => $this->price ?? 'NOT_SET',
                    'base_price' => $basePrice,
                    'price_with_rules' => $productPrice,
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'parent_product_id' => $parentProduct->id,
                    'parent_product_sku' => $parentProduct->sku,
                    'has_rules_applied' => $productPrice !== $basePrice,
                    'variant_passed_to_service' => $this->resource->id,
                ]);
            } else {
                // Для обычного товара — цена из модели; для вариативного — уже подставлена из самой дешёвой вариации
                $basePrice = (float) $this->price;
                if ($productPriceFromCheapestVariant !== null) {
                    $productPrice = $productPriceFromCheapestVariant;
                } else {
                    $productPrice = $regionRuleService->getPriceForRegion($this->resource, $region, null);
                }

                // Отладочный вывод для обычного товара
                Log::debug('ProductDetailResource: Применение правил для товара', [
                    'product_id' => $this->id,
                    'product_sku' => $this->sku,
                    'base_price' => $basePrice,
                    'price_with_rules' => $productPrice,
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                    'has_rules_applied' => $productPrice !== $basePrice,
                ]);
            }
        } else {
            // Без региона: для вариативного товара используем цену самой дешёвой вариации
            if ($productPriceFromCheapestVariant !== null) {
                $productPrice = $productPriceFromCheapestVariant;
            }
            Log::debug('ProductDetailResource: Регион не передан, используется базовая цена', [
                'product_id' => $this->id,
                'is_variant' => $isVariant,
                'price' => $productPrice,
            ]);
        }

        // Получаем срок доставки для невариативных товаров
        if (!$isVariable && $region) {
            if ($isVariant && $parentProduct) {
                // Для вариации: передаем родительский товар и саму вариацию
                $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($parentProduct, $region, $this->resource);
            } else {
                // Для обычного товара
                $deliveryDays = $regionRuleService->getDeliveryDaysForRegion($this->resource, $region);
            }
        }

        // Получаем блоки фич и доставки для товара (с учетом переопределения)
        // Используем eager loaded данные если доступны для оптимизации
        $featureBlocks = ProductFeatureBlock::getForProduct($this->resource)
            ->map(function ($block) {
                return [
                    'id' => $block->id,
                    'title' => $block->title,
                    'subtitle' => $block->subtitle,
                    'icon' => $block->icon && $block->icon !== '[]' ? $block->icon : null,
                    'icon_image' => $block->icon_image_url,
                    'icon_color' => $block->icon_color,
                    'bg_color' => $block->bg_color,
                ];
            })
            ->values()
            ->toArray();

        $deliveryBlocks = ProductDeliveryBlock::getForProduct($this->resource)
            ->map(function ($block) {
                return [
                    'id' => $block->id,
                    'title' => $block->title,
                    'description' => $block->description,
                    'icon' => $block->icon && $block->icon !== '[]' ? $block->icon : null,
                    'icon_image' => $block->icon_image_url,
                    'icon_color' => $block->icon_color,
                    'bg_color' => $block->bg_color,
                ];
            })
            ->values()
            ->toArray();

        // УПРОЩЕННАЯ ЛОГИКА: Всегда возвращаем данные напрямую
        // Для вариаций: sku и price берутся из самой вариации (с применением правил для цены)
        // ВАЖНО: Для вариаций всегда используем sku самой вариации, а не родительского товара
        // ПРЯМОЕ ОБРАЩЕНИЕ К АТРИБУТАМ МОДЕЛИ. Для вариативного товара — sku самой дешёвой вариации
        $sku = $skuFromCheapestVariant !== null && $skuFromCheapestVariant !== ''
            ? $skuFromCheapestVariant
            : ($this->attributes['sku'] ?? $this->sku ?? '');

        // Если это вариация и SKU пустой, проверяем родительский товар (но это не должно происходить)
        if ($isVariant && empty($sku) && $parentProduct) {
            Log::warning('ProductDetailResource: SKU вариации пустой, используем родительский', [
                'variant_id' => $this->id,
                'parent_sku' => $parentProduct->sku,
            ]);
            // НЕ используем родительский SKU - это ошибка данных
        }

        // Отладочный вывод для проверки данных вариации
        if ($isVariant) {
            Log::debug('ProductDetailResource для вариации - ФИНАЛЬНЫЕ ДАННЫЕ', [
                'variant_id' => $this->id,
                'variant_sku_final' => $sku,
                'variant_sku_from_attributes' => $this->attributes['sku'] ?? 'NOT_SET',
                'variant_sku_from_accessor' => $this->sku ?? 'NOT_SET',
                'variant_base_price' => $basePrice,
                'variant_price_with_rules' => $productPrice,
                'price_changed' => $productPrice !== $basePrice,
                'region_id' => $region?->id,
                'region_name' => $region?->name,
                'parent_product_id' => $this->parent_product_id,
                'has_parent' => $parentProduct !== null,
                'parent_sku' => $parentProduct?->sku ?? 'NO_PARENT',
                'is_variant_check' => $this->isVariant(),
            ]);
        }

        $resolvedStock = (int) round((float) ($warehouseStockResolver->resolveForProduct($this->resource, $region) ?? 0));
        $inStock = $resolvedStock > 0 || $this->backorder;

        // Для вариативного родителя: в наличии, если доступна хотя бы одна вариация
        if ($isVariable && ! $isVariant && ! empty($variants)) {
            $inStock = collect($variants)->contains(fn (array $variant): bool => (bool) ($variant['in_stock'] ?? false))
                || $this->backorder;
            if (! $inStock) {
                $resolvedStock = 0;
            } else {
                $resolvedStock = (int) collect($variants)
                    ->filter(fn (array $variant): bool => (bool) ($variant['in_stock'] ?? false))
                    ->max(fn (array $variant): int => (int) ($variant['stock'] ?? 0));
            }
        }

        $stockLabel = StockCategoryHelper::formatStockDisplay($resolvedStock);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $sku, // ВАЖНО: Для вариаций это артикул вариации
            'article' => $sku,
            'price' => $productPrice, // ВАЖНО: Цена с применением правил корзины
            'old_price' => $this->original_price ? (float) $this->original_price : null,
            'delivery_days' => $deliveryDays,
            'discount_percent' => $this->discount_percent,
            // Для детальной карточки: изображения по умолчанию в HD, с fallback на оригиналы
            'images' => $this->images_hd_urls ?: ($this->images_urls ?: ($this->main_image_url ? [$this->main_image_url] : [])),
            'main_image' => $this->image_hd_url ?: $this->main_image_url,
            'main_image_fullhd' => $this->image_fullhd_url ?: $this->main_image_url,
            'thumbnail' => $this->thumbnail_url,
            'in_stock' => $inStock,
            'stock' => $resolvedStock,
            'stock_label' => $stockLabel,
            'backorder' => (bool) $this->backorder,
            'rating' => $this->rating ?? 4.8,
            'reviews_count' => $this->reviews_count ?? 0,
            'description' => $this->description,
            //'excerpt' => $this->excerpt,
            'is_variable' => $isVariable,
            'is_variant' => $isVariant,
            'parent_id' => $this->when($isVariant, $this->parent_product_id),
            'variants' => $variants, // ВАЖНО: Все вариации для сравнения на фронтенде
            'category' => $this->when(
                $this->relationLoaded('taxons') && $this->taxons->isNotEmpty(),
                fn() => $this->taxons->first() ? [
                    'id' => $this->taxons->first()->id,
                    'name' => $this->taxons->first()->name,
                    'slug' => $this->taxons->first()->slug ?? Str::slug($this->taxons->first()->name),
                ] : null
            ),
            'categories' => CategoryResource::collection($this->whenLoaded('taxons')),
            'specifications' => $specifications,
            'variation_attributes' => $variationAttributes,
            'selected_variation' => $selectedVariation,
            'seo' => SeoApiTransformer::forModel($this->resource),
            'feature_blocks' => $featureBlocks,
            'delivery_blocks' => $deliveryBlocks,
            'colors' => $this->getColorsArray(),
        ];
    }
}
