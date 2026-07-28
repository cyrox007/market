<?php

namespace App\Models\Product;

use App\Contracts\Models\PageableContract;
use App\Models\Inventory\ProductWarehouseStock;
use App\Models\Page\InteriorIdeaHotspot;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;
use Vanilo\Category\Models\TaxonProxy;
use Vanilo\Contracts\Buyable;
use Vanilo\Product\Models\Product as VaniloProduct;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Manufacturer;
use App\Models\Product\ProductCollection;
use App\Models\Product\ProductDeliveryBlock;
use App\Models\Product\ProductFeatureBlock;
use App\Models\Product\ProductRegionRule;
use App\Models\Product\Review;

class Product extends VaniloProduct implements HasMedia, PageableContract, Buyable
{
    use Cacheable, HasFactory, InteractsWithMedia, Pageable, HasSEO, MetaUniversalSEO;

    public const ROOT_PATH = '/product';

    public const ACTIVE = 'active';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();

        // Сбрасываем кэш при любом изменении/создании товара (flushCache также вызывается из трейта Cacheable)
        static::saved(function ($product) {
            if ($product->wasChanged() || $product->wasRecentlyCreated) {
                $product->flushCategoryCaches();
                // Сбрасываем кэш товара и списков при любом изменении
                $product->flushCache();
            }
        });

        // Сбрасываем кэш при изменении вариаций и синхронизируем цену родителя
        static::saved(function ($product) {
            if ($product->isVariant() && $product->parent_product_id) {
                $parent = static::find($product->parent_product_id);
                if ($parent) {
                    $parent->flushCache();
                    static::syncParentPriceFromVariants($parent);
                }
            }
        });

        // У вариативных товаров поле price всегда = минимальная цена среди вариаций (для отображения в каталоге и при первом заходе)
        static::saved(function ($product) {
            if ($product->isVariable() && !$product->isVariant()) {
                static::syncParentPriceFromVariants($product);
            }
        });

        // Если артикул не задан, подставляем ID товара после создания
        static::created(function (self $product) {
            $sku = (string) ($product->attributes['sku'] ?? $product->sku ?? '');
            if ($sku === '' || $sku === null) {
                $product->forceFill(['sku' => (string) $product->getKey()])->saveQuietly();
            }
        });

        // Каскадная очистка хотспотов для совместимости со старыми данными
        // (в таблице hotspots нет FK on delete cascade).
        static::deleting(function (self $product) {
            if (Schema::hasTable('interior_idea_hotspots')) {
                InteriorIdeaHotspot::query()->where('product_id', $product->getKey())->delete();
            }
        });
    }

    /**
     * Установить цену родительского товара = минимум цен активных вариаций.
     * Вызывается при сохранении вариации или вариативного товара (для отображения в каталоге и при первом заходе).
     */
    public static function syncParentPriceFromVariants(self $parent): void
    {
        $minPrice = $parent->variants()->active()->min('price');
        if ($minPrice !== null) {
            $parent->updateQuietly(['price' => (float) $minPrice]);
        }
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ProductFactory::new();
    }

    /**
     * Get taxons (categories) for the product.
     */
    public function taxons(): MorphToMany
    {
        return $this->morphToMany(
            TaxonProxy::modelClass(),
            'model',
            'model_taxons',
            'model_id',
            'taxon_id'
        );
    }

    public function deliveryBlocks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductDeliveryBlock::class,
            'product_delivery_blocks_pivot',
            'product_id',
            'delivery_block_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    public function featureBlocks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductFeatureBlock::class,
            'product_feature_blocks_pivot',
            'product_id',
            'feature_block_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Получить производителя товара (из 1С).
     */
    public function manufacturer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    /**
     * Получить характеристики продукта
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_product_attributes',
            'product_id',
            'attribute_id'
        )->withPivot('attribute_value_id', 'custom_value')->withTimestamps();
    }

    /**
     * Получить значения характеристик продукта
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_product_attributes',
            'product_id',
            'attribute_value_id'
        )->withPivot('attribute_id', 'custom_value')->withTimestamps();
    }

    /**
     * Получить значение характеристики по slug характеристики
     */
    public function getProductAttributeValue(string $attributeSlug): ?AttributeValue
    {
        return $this->attributeValues()
            ->whereHas('attribute', function ($query) use ($attributeSlug) {
                $query->where('slug', $attributeSlug);
            })
            ->first();
    }

    /**
     * Коллекция характеристик для отображения в инфолисте (название и значение).
     *
     * @return \Illuminate\Support\Collection<int, array{name: string, value: string}>
     */
    public function getAttributesForInfolistAttribute(): Collection
    {
        $this->loadMissing(['attributes.values']);

        $attributes = $this->relationLoaded('attributes')
            ? $this->getRelation('attributes')
            : $this->attributes()->with('values')->get();

        return $attributes
            ->map(function (Attribute $a) {
                // Приоритет: кастомное значение, затем значение из справочника
                $pivot = $a->pivot;
                $value = null;

                if ($pivot && $pivot->custom_value !== null && $pivot->custom_value !== '') {
                    $value = $pivot->custom_value;
                } else {
                    $value = $a->values->firstWhere('id', $pivot?->attribute_value_id)?->value ?? null;
                }

                return [
                    'name' => $a->name,
                    'value' => $value ?? '—',
                ];
            })
            ->values();
    }

    /**
     * Характеристики для API (карточка товара, торговые предложения).
     *
     * @return list<array{name: string, value: string, slug: string}>
     */
    public function buildSpecificationsForApi(): array
    {
        $this->loadMissing(['attributes']);

        $specifications = [];

        $productAttributes = $this->relationLoaded('attributes')
            ? $this->getRelation('attributes')
            : $this->attributes()->get();

        foreach ($productAttributes as $attribute) {
            $pivot = $attribute->pivot;
            $value = null;

            if ($pivot && $pivot->custom_value !== null && $pivot->custom_value !== '') {
                $value = (string) $pivot->custom_value;
            } elseif ($pivot?->attribute_value_id) {
                $attribute->loadMissing('values');
                $value = $attribute->values->firstWhere('id', $pivot->attribute_value_id)?->value;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $specifications[] = [
                'name' => $attribute->name,
                'value' => $value,
                'slug' => $attribute->slug,
            ];
        }

        if ($this->length || $this->width || $this->height || $this->weight) {
            $specifications[] = [
                'name' => 'Размеры (ДxШxВ)',
                'value' => ($this->length ?? '-') . ' x ' . ($this->width ?? '-') . ' x ' . ($this->height ?? '-') . ' см',
                'slug' => 'dimensions',
            ];
            if ($this->weight) {
                $specifications[] = [
                    'name' => 'Вес',
                    'value' => $this->weight . ' кг',
                    'slug' => 'weight',
                ];
            }
        }

        return $specifications;
    }

    /**
     * Проверить, является ли продукт вариативным
     */
    public function isVariable(): bool
    {
        return (bool) ($this->attributes['is_variable'] ?? $this->is_variable ?? false);
    }

    /**
     * Проверить, является ли продукт вариацией
     */
    public function isVariant(): bool
    {
        return !is_null($this->attributes['parent_product_id'] ?? $this->parent_product_id ?? null);
    }

    /**
     * Получить родительский продукт (для вариаций)
     */
    public function parentProduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /**
     * Получить все вариации продукта
     */
    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    /**
     * Получить подборки, в которых находится товар
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'product_product_collection')
            ->withTimestamps()
            ->withPivot('sort_order');
    }

    /**
     * Получить сопутствующие товары (ручные связи товаров между собой).
     *
     * ВАЖНО: Связь симметричная – при привязке товара A к товару B,
     * к товару B автоматически привязывается товар A (реализовано в методах attach/detach).
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_products',
            'product_id',
            'related_product_id'
        )->withTimestamps();
    }

    /**
     * Получить массив цветов для товара
     */
    public function getColorsArray(): array
    {
        $colors = [];
        
        // Ищем характеристику "Цвет"
        $colorAttribute = Attribute::where('slug', 'color')->first();
        if (!$colorAttribute) {
            return $colors;
        }
        
        // Получаем значения цвета через характеристики
        $colorValues = $this->attributeValues()
            ->whereHas('attribute', function ($q) use ($colorAttribute) {
                $q->where('id', $colorAttribute->id);
            })
            ->get();
        
        foreach ($colorValues as $value) {
            $colors[] = [
                'id' => $value->id,
                'value' => $value->value,
                'slug' => $value->slug,
                'color_code' => $value->color_code,
            ];
        }
        
        return $colors;
    }

    /**
     * Привязать сопутствующий товар с автоматическим добавлением обратной связи.
     */
    public function attachRelatedProduct(Product $related): void
    {
        if ($this->is($related)) {
            // Не даем привязывать товар сам к себе
            return;
        }

        // Привязываем в обоих направлениях без дублирования
        $this->relatedProducts()->syncWithoutDetaching([$related->getKey()]);
        $related->relatedProducts()->syncWithoutDetaching([$this->getKey()]);

        // Сбрасываем кэши, чтобы блок "С этим товаром покупают" обновился
        static::flushAllProductCaches();
    }

    /**
     * Отвязать сопутствующий товар с удалением обратной связи.
     */
    public function detachRelatedProduct(Product $related): void
    {
        if ($this->is($related)) {
            return;
        }

        $this->relatedProducts()->detach($related->getKey());
        $related->relatedProducts()->detach($this->getKey());

        static::flushAllProductCaches();
    }

    /**
     * Товары, входящие в набор / комплект (односторонняя связь).
     */
    public function bundleProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_bundle_products',
            'product_id',
            'bundle_product_id'
        )
            ->withTimestamps()
            ->withPivot('sort_order')
            ->orderBy('product_bundle_products.sort_order');
    }

    /**
     * @param  array<int, int|string>  $productIds
     * @return array{attached: int[], skipped: int[]}
     */
    public function attachBundleProducts(array $productIds): array
    {
        $ownerId = (int) $this->getKey();
        $existingIds = $this->bundleProducts()->pluck('products.id')->map(fn ($id) => (int) $id)->all();
        $maxOrder = (int) ($this->bundleProducts()->max('product_bundle_products.sort_order') ?? 0);

        $attached = [];
        $skipped = [];

        foreach ($productIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0 || $id === $ownerId || in_array($id, $existingIds, true)) {
                $skipped[] = $id;

                continue;
            }

            $maxOrder++;
            $this->bundleProducts()->attach($id, ['sort_order' => $maxOrder]);
            $existingIds[] = $id;
            $attached[] = $id;
        }

        if ($attached !== []) {
            $this->flushCache();
            static::flushAllProductCaches();
        }

        \Illuminate\Support\Facades\Log::info('[Product.attachBundleProducts]', [
            'owner_id' => $ownerId,
            'attached_ids' => $attached,
            'skipped_ids' => $skipped,
        ]);

        return ['attached' => $attached, 'skipped' => $skipped];
    }

    public function detachBundleProduct(Product $bundleProduct): void
    {
        if ($this->is($bundleProduct)) {
            return;
        }

        $this->bundleProducts()->detach($bundleProduct->getKey());
        $this->flushCache();
        static::flushAllProductCaches();

        \Illuminate\Support\Facades\Log::info('[Product.detachBundleProduct]', [
            'owner_id' => $this->getKey(),
            'bundle_product_id' => $bundleProduct->getKey(),
        ]);
    }

    /**
     * Получить отзывы продукта
     */
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Получить одобренные отзывы продукта
     */
    public function approvedReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * Получить региональные правила для товара
     */
    public function regionRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductRegionRule::class, 'product_id');
    }

    /**
     * Получить региональные правила для вариации
     */
    public function variantRegionRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductRegionRule::class, 'variant_id');
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }


    /**
     * Рассчитать средний рейтинг продукта из одобренных отзывов
     */
    public function averageRating(): float
    {
        $approvedReviews = $this->approvedReviews()->get();

        if ($approvedReviews->isEmpty()) {
            return 0.0;
        }

        return round($approvedReviews->avg('rating'), 1);
    }

    /**
     * Получить вариации с загруженными характеристиками
     */
    public function variantsWithAttributes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->variants()->active()->with(['attributeValues.attribute']);
    }

    /**
     * Получить характеристики вариации (через product_variant_attributes)
     */
    public function variantAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_variant_attributes',
            'product_id',
            'attribute_id'
        )->withPivot('attribute_value_id', 'custom_value')->withTimestamps();
    }

    /**
     * Получить значения характеристик вариации
     */
    public function variantAttributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attributes',
            'product_id',
            'attribute_value_id'
        )->withPivot('attribute_id', 'custom_value')->withTimestamps();
    }

    /**
     * Для родительского товара: какие атрибуты вариаций использовать (если пусто — все с is_use_in_variations).
     */
    public function variationAttributeSelection(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'product_variation_attribute_selection',
            'product_id',
            'attribute_id'
        )->withTimestamps();
    }

    /**
     * Slug'и атрибутов вариаций для этого товара: либо выбранные для товара, либо все глобальные.
     */
    public function getVariationAttributeSlugs(): Collection
    {
        if ($this->isVariable() && !$this->isVariant() && \Schema::hasTable('product_variation_attribute_selection')) {
            $selected = $this->variationAttributeSelection()->pluck('slug');
            if ($selected->isNotEmpty()) {
                return $selected;
            }
        }
        return Attribute::variationAttributes()->pluck('slug');
    }

    /**
     * Доступные значения для одного атрибута вариаций (включая ручной ввод custom_value).
     */
    public function getAvailableValuesForVariationAttribute(string $attributeSlug): Collection
    {
        if (!$this->isVariable()) {
            return collect();
        }

        $attribute = Attribute::where('slug', $attributeSlug)->where('is_use_in_variations', true)->first();
        if (!$attribute) {
            return collect();
        }

        $rows = \DB::table('product_variant_attributes')
            ->join('products', 'products.id', '=', 'product_variant_attributes.product_id')
            ->where('product_variant_attributes.attribute_id', $attribute->id)
            ->where('products.parent_product_id', $this->id)
            ->where('products.state', self::ACTIVE)
            ->select('product_variant_attributes.attribute_value_id', 'product_variant_attributes.custom_value')
            ->distinct()
            ->get();

        $valueIds = $rows->pluck('attribute_value_id')->filter()->unique()->values();
        $customValues = $rows->pluck('custom_value')->filter()->unique()->values();

        $result = collect();
        if ($valueIds->isNotEmpty()) {
            $predefined = AttributeValue::whereIn('id', $valueIds)->orderBy('sort_order')->get();
            foreach ($predefined as $av) {
                $result->push((object) [
                    'id' => $av->id,
                    'value' => $av->value,
                    'name' => $av->value,
                    'slug' => $av->slug,
                    'color_code' => $attribute->type === 'color' ? ($av->color_code ?? null) : null,
                ]);
            }
        }
        foreach ($customValues as $custom) {
            $result->push((object) [
                'id' => null,
                'value' => $custom,
                'name' => $custom,
                'slug' => \Str::slug($custom),
                'color_code' => null,
            ]);
        }
        return $result;
    }

    /**
     * Найти вариацию по набору атрибутов. $attributes = ['attribute_slug' => 'value_slug_or_value', ...].
     * Поддерживает и справочные значения (attribute_value_id), и ручной ввод (custom_value).
     */
    public function getVariantByVariationAttributes(array $attributes): ?Product
    {
        if (!$this->isVariable() || empty($attributes)) {
            return $this->isVariable() ? null : $this;
        }

        $attributeSlugs = array_keys($attributes);
        $attrs = Attribute::whereIn('slug', $attributeSlugs)->where('is_use_in_variations', true)->get();
        if ($attrs->count() !== count($attributeSlugs)) {
            return null;
        }

        $query = $this->variants()->active();
        $variantIds = $this->variants()->active()->pluck('id');

        foreach ($attrs as $attr) {
            $input = $attributes[$attr->slug] ?? null;
            if ($input === null || $input === '') {
                continue;
            }

            $inputSlug = \Str::slug($input);
            $valueIds = AttributeValue::where('attribute_id', $attr->id)
                ->where(function ($q) use ($input, $inputSlug) {
                    $q->where('slug', $inputSlug)
                        ->orWhere('slug', $input)
                        ->orWhere('value', $input)
                        ->orWhereRaw('LOWER(REPLACE(value, " ", "")) = ?', [strtolower(str_replace(' ', '', $input))]);
                })
                ->pluck('id');

            // Сопоставление custom_value в PHP: в БД только текст, slug считаем здесь.
            $matchingCustomValues = collect();
            if (($input !== '' || $inputSlug !== '') && $variantIds->isNotEmpty()) {
                $customValues = \DB::table('product_variant_attributes')
                    ->whereIn('product_id', $variantIds)
                    ->where('attribute_id', $attr->id)
                    ->whereNotNull('custom_value')
                    ->where('custom_value', '!=', '')
                    ->distinct()
                    ->pluck('custom_value');
                $matchingCustomValues = $customValues->filter(function ($cv) use ($input, $inputSlug) {
                    if ($cv === $input) {
                        return true;
                    }
                    if (trim(mb_strtolower($cv)) === trim(mb_strtolower($input))) {
                        return true;
                    }
                    return \Str::slug($cv) === $inputSlug || \Str::slug($cv) === $input;
                })->values();
            }

            if ($valueIds->isEmpty() && $matchingCustomValues->isEmpty()) {
                return null;
            }

            $query->whereExists(function ($q) use ($attr, $valueIds, $matchingCustomValues) {
                $q->from('product_variant_attributes as pva')
                    ->whereColumn('pva.product_id', 'products.id')
                    ->where('pva.attribute_id', $attr->id)
                    ->where(function ($q2) use ($valueIds, $matchingCustomValues) {
                        if ($valueIds->isNotEmpty()) {
                            $q2->whereIn('pva.attribute_value_id', $valueIds);
                        }
                        if ($matchingCustomValues->isNotEmpty()) {
                            $q2->orWhereIn('pva.custom_value', $matchingCustomValues->toArray());
                        }
                    });
            });
        }

        return $query->first();
    }

    /**
     * Для вариации: массив атрибутов вариации в формате API (включая ручной ввод custom_value).
     */
    public function getVariationAttributesForApi(): array
    {
        if (!$this->isVariant()) {
            return [];
        }
        $rows = \DB::table('product_variant_attributes')
            ->where('product_id', $this->id)
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $attr = Attribute::find($row->attribute_id);
            if (!$attr || !$attr->is_use_in_variations) {
                continue;
            }
            if (!empty($row->custom_value)) {
                $out[] = [
                    'attribute_slug' => $attr->slug,
                    'value_slug' => \Str::slug($row->custom_value),
                    'value_name' => $row->custom_value,
                    'code' => null,
                ];
                continue;
            }
            if (empty($row->attribute_value_id)) {
                continue;
            }
            $av = AttributeValue::find($row->attribute_value_id);
            if (!$av) {
                continue;
            }
            $out[] = [
                'attribute_slug' => $attr->slug,
                'value_slug' => $av->slug,
                'value_name' => $av->value,
                'code' => $attr->type === 'color' ? ($av->color_code ?? null) : null,
            ];
        }
        return $out;
    }

    /**
     * Получить доступные цвета для вариаций
     * Цвета теперь хранятся в полях color и color_code вариаций
     */
    public function getAvailableColors(): Collection
    {
        if (!$this->isVariable()) {
            // Для невариативных товаров возвращаем цвет самого товара
            if ($this->color) {
                return collect([
                    (object) [
                        'id' => 1,
                        'value' => $this->color,
                        'slug' => \Str::slug($this->color),
                        'color_code' => $this->color_code,
                    ]
                ]);
            }
            return collect();
        }

        // Получаем уникальные цвета из всех вариаций (используем уже загруженные, чтобы избежать N+1)
        $variantsCollection = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->active()->get();

        $colors = $variantsCollection
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->map(fn ($item) => (object) ['color' => $item->color, 'color_code' => $item->color_code ?? null])
            ->unique(function ($item) {
                return ($item->color ?? '') . ($item->color_code ?? '');
            })
            ->values();

        // Преобразуем в коллекцию объектов для совместимости с API
        return $colors->map(function ($color, $index) {
            return (object) [
                'id' => $index + 1,
                'value' => $color->color,
                'slug' => \Str::slug($color->color),
                'color_code' => $color->color_code,
            ];
        });
    }

    /**
     * Получить доступные размеры для вариаций
     * Размеры вычисляются из length x width x height вариаций
     */
    public function getAvailableSizes(): Collection
    {
        if (!$this->isVariable()) {
            // Для невариативных товаров возвращаем размер самого товара
            if ($this->length && $this->width && $this->height) {
                $sizeStr = "{$this->length}x{$this->width}";
                return collect([
                    (object) [
                        'id' => 1,
                        'value' => $sizeStr,
                        'slug' => \Str::slug($sizeStr),
                        'name' => "{$this->length} x {$this->width} см",
                    ]
                ]);
            }
            return collect();
        }

        // Получаем уникальные размеры из вариаций (length x width; height не обязателен)
        $variants = $this->variants()
            ->active()
            ->whereNotNull('length')
            ->whereNotNull('width')
            ->select('length', 'width', 'height')
            ->distinct()
            ->get();

        // Группируем по length x width (высота может отличаться)
        $sizes = $variants->map(function ($variant) {
            return (object) [
                'length' => $variant->length,
                'width' => $variant->width,
                'height' => $variant->height,
                'key' => "{$variant->length}x{$variant->width}",
            ];
        })->unique('key')->values();

        // Преобразуем в коллекцию объектов для совместимости с API
        return $sizes->map(function ($size, $index) {
            $sizeStr = $size->key;
            return (object) [
                'id' => $index + 1,
                'value' => $sizeStr,
                'slug' => \Str::slug($sizeStr),
                'name' => "{$size->length} x {$size->width} см",
            ];
        });
    }

    /**
     * Получить вариацию по цвету и размеру
     * Размер передается как строка "280x180" (length x width)
     * ВАЖНО: Если переданы оба параметра (цвет и размер), ищется точная вариация
     * Если передан только один параметр, возвращается первая подходящая вариация
     */
    public function getVariantByAttributes(?string $color = null, ?string $size = null): ?Product
    {
        if (!$this->isVariable()) {
            // Для невариативных товаров возвращаем сам товар
            return $this;
        }

        $query = $this->variants()->active();

        // Цвет хранится в поле color вариации
        if ($color) {
            // Получаем все уникальные цвета из вариаций для сравнения
            $allColors = $this->variants()
                ->active()
                ->whereNotNull('color')
                ->where('color', '!=', '')
                ->select('color')
                ->distinct()
                ->pluck('color')
                ->toArray();

            // Нормализуем переданный цвет (может быть название или slug)
            // Фронтенд может передать как "Белая", так и "belaia" (slug)
            $colorSlug = \Str::slug($color);
            $matchingColors = [];

            // Ищем точное совпадение или совпадение по slug
            foreach ($allColors as $dbColor) {
                $dbColorSlug = \Str::slug($dbColor);
                // Сравниваем: точное совпадение, совпадение по slug, или обратное (если передан slug, ищем по name)
                if (
                    $dbColor === $color ||
                    $dbColorSlug === $colorSlug ||
                    $dbColorSlug === $color ||
                    $dbColor === $colorSlug
                ) {
                    $matchingColors[] = $dbColor;
                }
            }

            \Log::debug('Product::getVariantByAttributes - Поиск цвета', [
                'product_id' => $this->id,
                'color_input' => $color,
                'color_slug' => $colorSlug,
                'all_colors_in_db' => $allColors,
                'matching_colors' => $matchingColors,
            ]);

            if (count($matchingColors) > 0) {
                $query->whereIn('color', $matchingColors);
            } else {
                // Если не нашли точного совпадения, возвращаем null
                \Log::warning('Product::getVariantByAttributes - Цвет не найден', [
                    'product_id' => $this->id,
                    'color_input' => $color,
                    'available_colors' => $allColors,
                ]);
                return null;
            }
        }

        // Размер передается как "280x180" (length x width)
        // Фронтенд может передать как "210x160", так и "210 x 160 см" или "210x160"
        if ($size) {
            // Убираем пробелы и приводим к нижнему регистру
            $normalizedSize = strtolower(str_replace([' ', 'см', 'cm'], '', $size));
            $sizeParts = explode('x', $normalizedSize);

            if (count($sizeParts) >= 2) {
                $length = (int) trim($sizeParts[0]);
                $width = (int) trim($sizeParts[1]);

                \Log::debug('Product::getVariantByAttributes - Поиск размера', [
                    'product_id' => $this->id,
                    'size_input' => $size,
                    'normalized_size' => $normalizedSize,
                    'length' => $length,
                    'width' => $width,
                ]);

                // Ищем точное совпадение по длине и ширине
                $query->where('length', $length)
                    ->where('width', $width);
            } else {
                \Log::warning('Product::getVariantByAttributes - Неверный формат размера', [
                    'product_id' => $this->id,
                    'size_input' => $size,
                    'normalized_size' => $normalizedSize,
                ]);
            }
        }

        // ВАЖНО: Если переданы оба параметра (цвет и размер), должна найтись ТОЧНАЯ вариация
        // Если передано только одно условие, возвращаем первую подходящую
        $variant = $query->first();

        // Если не нашли вариацию, но переданы оба параметра, это ошибка
        if (!$variant && $color && $size) {
            // Логируем для отладки (можно убрать в продакшене)
            \Log::warning("Variant not found", [
                'product_id' => $this->id,
                'color' => $color,
                'size' => $size,
            ]);
        }

        return $variant;
    }

    /**
     * Получить доступные размеры для конкретного цвета
     * @param string|null $color - название цвета (например "Белая") или slug (например "belaia")
     */
    public function getAvailableSizesForColor(?string $color = null): Collection
    {
        if (!$this->isVariable()) {
            return $this->getAvailableSizes();
        }

        $query = $this->variants()
            ->active()
            ->whereNotNull('length')
            ->whereNotNull('width');

        if ($color) {
            // Получаем все уникальные цвета из вариаций для сравнения
            $allColors = $this->variants()
                ->active()
                ->whereNotNull('color')
                ->where('color', '!=', '')
                ->select('color')
                ->distinct()
                ->pluck('color')
                ->toArray();

            // Нормализуем переданный цвет (может быть название или slug)
            $colorSlug = \Str::slug($color);
            $matchingColors = [];

            // Ищем точное совпадение или совпадение по slug
            foreach ($allColors as $dbColor) {
                $dbColorSlug = \Str::slug($dbColor);
                if ($dbColor === $color || $dbColorSlug === $colorSlug || $dbColorSlug === $color) {
                    $matchingColors[] = $dbColor;
                }
            }

            if (count($matchingColors) > 0) {
                $query->whereIn('color', $matchingColors);
            } else {
                // Если не нашли точного совпадения, возвращаем пустую коллекцию
                return collect();
            }
        }

        $variants = $query->select('length', 'width', 'height')
            ->distinct()
            ->get();

        $sizes = $variants->map(function ($variant) {
            return (object) [
                'length' => $variant->length ?? null,
                'width' => $variant->width ?? null,
                'height' => $variant->height ?? null,
                'key' => ($variant->length ?? 0) . 'x' . ($variant->width ?? 0),
            ];
        })->unique('key')->values();

        return $sizes->map(function ($size, $index) {
            return (object) [
                'id' => $index + 1,
                'value' => $size->key ?? null,
                'slug' => \Str::slug($size->key ?? ''),
                'name' => ($size->length ?? 0) . ' x ' . ($size->width ?? 0) . ' см',
            ];
        });
    }

    /**
     * Получить доступные цвета для конкретного размера
     */
    public function getAvailableColorsForSize(?string $size = null): Collection
    {
        if (!$this->isVariable()) {
            return $this->getAvailableColors();
        }

        $query = $this->variants()
            ->active()
            ->whereNotNull('color')
            ->where('color', '!=', '');

        if ($size) {
            $sizeParts = explode('x', $size);
            if (count($sizeParts) === 2) {
                $length = (int) trim($sizeParts[0]);
                $width = (int) trim($sizeParts[1]);
                $query->where('length', $length)
                    ->where('width', $width);
            }
        }

        $colors = $query->select('color', 'color_code')
            ->distinct()
            ->orderBy('color')
            ->get()
            ->unique(function ($item) {
                return $item->color . $item->color_code;
            })
            ->values();

        return $colors->map(function ($color, $index) {
            return (object) [
                'id' => $index + 1,
                'value' => $color->color,
                'slug' => \Str::slug($color->color),
                'color_code' => $color->color_code,
            ];
        });
    }

    /**
     * Get main category (first taxon).
     */
    public function category()
    {
        return $this->taxons()->first();
    }

    /**
     * Get root path constant.
     */
    public static function getRootPath(): string
    {
        return self::ROOT_PATH;
    }

    /**
     * Get active status constant.
     */
    public static function getActiveConstant(): string
    {
        return self::ACTIVE;
    }

    /**
     * Get the name of the active field.
     */
    public static function getActiveFieldName(): string
    {
        return 'state';
    }

    /**
     * Get the name of the sort field.
     */
    public static function getSortFieldName(): string
    {
        return 'priority';
    }

    /**
     * Get full URL path for the product.
     */
    public function getFullPathAttribute(): ?string
    {
        // Загружаем taxons если они не загружены
        if (!$this->relationLoaded('taxons')) {
            $this->load('taxons');
        }

        $category = $this->taxons->first();

        if (!$category) {
            return self::ROOT_PATH . '/' . $this->slug;
        }

        // Загружаем parent для категории если нужно
        if (!$category->relationLoaded('parent') && $category->parent_id) {
            $category->load('parent');
        }

        $categoryPath = $category->full_path;

        if ($categoryPath) {
            // Убираем начальный слэш из categoryPath если он есть
            $categoryPath = ltrim($categoryPath, '/');
            return '/' . $categoryPath . '/' . $this->slug;
        }

        return self::ROOT_PATH . '/' . $this->slug;
    }

    /**
     * Scope a query to only include featured products.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        // Можно добавить поле is_featured в будущем
        return $query->where('units_sold', '>', 0)
            ->orderBy('units_sold', 'desc');
    }

    /**
     * Scope a query to only include new products.
     */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to only include products on sale.
     */
    public function scopeSale(Builder $query): Builder
    {
        return $query->whereColumn('original_price', '>', 'price')
            ->whereNotNull('original_price')
            ->whereNotNull('price');
    }

    /**
     * Scope a query to filter by category (taxon) including all descendant categories.
     */
    public function scopeInCategory(Builder $query, $taxonId): Builder
    {
        $categoryIds = Category::getAllDescendantIdsFor($taxonId);

        return $query->whereHas('taxons', function ($q) use ($categoryIds) {
            $q->whereIn('taxons.id', $categoryIds);
        });
    }

    /**
     * Scope a query to search products.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $searchTerm = '%' . $term . '%';
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', $searchTerm)
                ->orWhere('description', 'like', $searchTerm)
                //->orWhere('excerpt', 'like', $searchTerm)
                ->orWhere('sku', 'like', $searchTerm);
        });
    }

    /**
     * Get the product price with discount.
     */
    public function getDiscountPriceAttribute(): ?float
    {
        $originalPrice = $this->attributes['original_price'] ?? null;
        $price = $this->attributes['price'] ?? 0;

        if ($originalPrice && $originalPrice > $price) {
            return (float) $price;
        }
        return null;
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentAttribute(): ?int
    {
        $originalPrice = $this->attributes['original_price'] ?? null;
        $price = $this->attributes['price'] ?? 0;

        if ($originalPrice && $originalPrice > $price) {
            return round((1 - $price / $originalPrice) * 100);
        }
        return null;
    }

    /**
     * Get stock attribute.
     * Для вариативных товаров возвращает сумму остатков всех активных вариаций.
     * Для «сольного» товара (is_variable=true, но 0 вариаций) — остаток самого товара.
     */
    public function getStockAttribute(): ?int
    {
        // Если это вариативный товар (не вариация), вычисляем сумму остатков вариаций
        if ($this->isVariable() && !$this->isVariant()) {
            $variants = $this->relationLoaded('variants') ? $this->getRelation('variants') : $this->variants;
            $variantsCount = $variants instanceof \Illuminate\Database\Eloquent\Collection
                ? $variants->count()
                : $this->variants()->count();

            // «Сольной» товар: is_variable, но вариаций нет — используем остаток самого товара
            if ($variantsCount === 0) {
                return isset($this->attributes['stock']) ? (int) $this->attributes['stock'] : null;
            }

            // Используем загруженные отношения если они есть, иначе делаем запрос
            if ($this->relationLoaded('variants')) {
                $variants = $this->getRelation('variants');
                $totalStock = $variants
                    ->filter(function ($variant) {
                        $state = $variant->attributes['state'] ?? $variant->state ?? null;
                        return $state === null || $state === self::ACTIVE;
                    })
                    ->sum(function ($variant) {
                        return (int) ($variant->attributes['stock'] ?? $variant->stock ?? 0);
                    });
                return (int) $totalStock;
            }

            $totalStock = $this->variants()->active()->sum('stock');
            return (int) $totalStock;
        }

        // Для обычных товаров и вариаций возвращаем значение из БД
        return isset($this->attributes['stock']) ? (int) $this->attributes['stock'] : null;
    }

    /**
     * Check if product is in stock.
     * Для вариативных товаров проверяет наличие хотя бы одной доступной вариации.
     * Для «сольного» товара (is_variable=true, но 0 вариаций) — наличие самого товара.
     */
    public function getInStockAttribute(): bool
    {
        // Если это вариативный товар (не вариация), проверяем вариации
        if ($this->isVariable() && !$this->isVariant()) {
            $variants = $this->relationLoaded('variants') ? $this->getRelation('variants') : $this->variants;
            $variantsCount = $variants instanceof \Illuminate\Database\Eloquent\Collection
                ? $variants->count()
                : $this->variants()->count();

            // «Сольной» товар: is_variable, но вариаций нет — проверяем наличие самого товара
            if ($variantsCount === 0) {
                $stock = (int) ($this->attributes['stock'] ?? 0);
                $backorder = (bool) ($this->attributes['backorder'] ?? false);
                return $stock > 0 || $backorder;
            }

            // Используем загруженные отношения если они есть, иначе делаем запрос
            if ($this->relationLoaded('variants')) {
                $variants = $this->getRelation('variants');
                $hasAvailableVariant = $variants->contains(function ($variant) {
                    $stock = (int) ($variant->attributes['stock'] ?? $variant->stock ?? 0);
                    $backorder = (bool) ($variant->attributes['backorder'] ?? $variant->backorder ?? false);
                    return $stock > 0 || $backorder;
                });
                return $hasAvailableVariant;
            }

            return $this->variants()
                ->active()
                ->where(function ($query) {
                    $query->where('stock', '>', 0)
                        ->orWhere('backorder', true);
                })
                ->exists();
        }

        // Для обычных товаров и вариаций используем стандартную логику
        $stock = $this->attributes['stock'] ?? 0;
        $backorder = $this->attributes['backorder'] ?? false;

        return $stock > 0 || $backorder;
    }

    /**
     * Get average rating from approved reviews.
     * Для вариаций возвращает рейтинг родительского товара.
     * Использует withCount для оптимизации запросов.
     */
    public function getRatingAttribute(): float
    {
        // Если это вариация, используем рейтинг родительского товара
        if ($this->isVariant() && $this->parent_product_id) {
            // Пытаемся получить родительский товар
            $parentProduct = $this->relationLoaded('parentProduct')
                ? $this->parentProduct
                : ($this->parent_product_id ? Product::find($this->parent_product_id) : null);

            if ($parentProduct) {
                // Используем прямой доступ к родительскому товару, чтобы избежать рекурсии
                try {
                    if (!Schema::hasTable('reviews')) {
                        return 0.0;
                    }
                    $parentReviews = Review::where('product_id', $parentProduct->id)
                        ->where('is_approved', true)
                        ->get();

                    if ($parentReviews->isEmpty()) {
                        return 0.0;
                    }

                    return round($parentReviews->avg('rating'), 1);
                } catch (\Exception $e) {
                    \Log::warning('Error getting parent rating for variant ' . $this->id . ': ' . $e->getMessage());
                    return 0.0;
                }
            }
        }

        // Если reviews_count загружен через withCount, используем его для расчета рейтинга
        // Но для рейтинга нужен отдельный запрос, поэтому используем кэширование
        if (array_key_exists('rating', $this->attributes)) {
            return (float) $this->attributes['rating'];
        }

        // Если отношение загружено, используем его
        if ($this->relationLoaded('approvedReviews')) {
            $reviews = $this->getRelation('approvedReviews');
            if ($reviews->isEmpty()) {
                return 0.0;
            }
            return round($reviews->avg('rating'), 1);
        }

        // Если модель не существует, возвращаем 0
        if (!$this->exists) {
            return 0.0;
        }

        // Пытаемся получить рейтинг через запрос
        try {
            // Проверяем, существует ли таблица reviews
            if (!Schema::hasTable('reviews')) {
                return 0.0;
            }
            return $this->averageRating();
        } catch (\Exception $e) {
            // Если таблица reviews не существует или другая ошибка, возвращаем 0
            \Log::warning('Error getting rating for product ' . $this->id . ': ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get count of approved reviews.
     * Для вариаций возвращает количество отзывов родительского товара.
     * Использует withCount для оптимизации запросов.
     */
    public function getReviewsCountAttribute(): int
    {
        // Если это вариация, используем количество отзывов родительского товара
        if ($this->isVariant() && $this->parent_product_id) {
            // Пытаемся получить родительский товар
            $parentProduct = $this->relationLoaded('parentProduct')
                ? $this->parentProduct
                : ($this->parent_product_id ? Product::find($this->parent_product_id) : null);

            if ($parentProduct) {
                // Используем прямой запрос, чтобы избежать рекурсии
                try {
                    if (!Schema::hasTable('reviews')) {
                        return 0;
                    }
                    return Review::where('product_id', $parentProduct->id)
                        ->where('is_approved', true)
                        ->count();
                } catch (\Exception $e) {
                    \Log::warning('Error getting parent reviews_count for variant ' . $this->id . ': ' . $e->getMessage());
                    return 0;
                }
            }
        }

        // Если reviews_count загружен через withCount, используем его
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        // Если отношение загружено, используем его
        if ($this->relationLoaded('approvedReviews')) {
            return $this->getRelation('approvedReviews')->count();
        }

        // Если модель не существует, возвращаем 0
        if (!$this->exists) {
            return 0;
        }

        // Пытаемся получить количество через запрос
        try {
            // Проверяем, существует ли таблица reviews
            if (!Schema::hasTable('reviews')) {
                return 0;
            }
            return $this->approvedReviews()->count();
        } catch (\Exception $e) {
            // Если таблица reviews не существует или другая ошибка, возвращаем 0
            \Log::warning('Error getting reviews_count for product ' . $this->id . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get thumbnail image URL.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->getUrl('thumb') : null;
    }

    /**
     * Get main image URL.
     */
    public function getMainImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->getUrl() : null;
    }

    /**
     * Get HD image URL (medium size).
     */
    public function getImageHdUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('images');

        return $media ? $media->getUrl('hd') : null;
    }

    /**
     * Get Full HD image URL (large size).
     */
    public function getImageFullhdUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('images');

        return $media ? $media->getUrl('fullhd') : null;
    }

    /**
     * Get all images URLs (main image + gallery).
     */
    public function getImagesUrlsAttribute(): array
    {
        $urls = [];

        // Main image
        $mainImage = $this->getFirstMedia('images');
        if ($mainImage) {
            $urls[] = $mainImage->getUrl();
        }

        // Gallery images
        $galleryImages = $this->getMedia('gallery')
            ->sortBy(function ($media) {
                return [$media->order_column ?? PHP_INT_MAX, $media->id];
            });
        foreach ($galleryImages as $image) {
            $urls[] = $image->getUrl();
        }

        return $urls;
    }

    /**
     * Get all HD images URLs (main image + gallery).
     */
    public function getImagesHdUrlsAttribute(): array
    {
        $urls = [];

        // Main image
        $mainImage = $this->getFirstMedia('images');
        if ($mainImage) {
            $urls[] = $mainImage->getUrl('hd');
        }

        // Gallery images
        $galleryImages = $this->getMedia('gallery')
        ->sortBy(function ($media) {
            return [$media->order_column ?? PHP_INT_MAX, $media->id];
        });
        foreach ($galleryImages as $image) {
            $urls[] = $image->getUrl('hd');
        }

        return $urls;
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile(); // Для главного изображения

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Конверсия для миниатюр (thumb)
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->fit(Fit::Crop, 300, 300)
            ->optimize()
            ->performOnCollections('images', 'gallery');

        // Конверсия для основного изображения (Full HD)
        $this->addMediaConversion('fullhd')
            ->width(1920)
            ->height(1080)
            ->fit(Fit::Contain, 1920, 1080)
            ->optimize()
            ->performOnCollections('images', 'gallery');

        // Конверсия для среднего размера (HD)
        $this->addMediaConversion('hd')
            ->width(1280)
            ->height(720)
            ->fit(Fit::Contain, 1280, 720)
            ->optimize()
            ->performOnCollections('images', 'gallery');
    }

    // Buyable interface methods

    /**
     * Get the ID of the buyable item.
     */
    public function getId(): string|int
    {
        return $this->id;
    }

    /**
     * Get the name of the buyable item.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the price of the buyable item.
     */
    public function getPrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    /**
     * Get the original price of the buyable item.
     */
    public function getOriginalPrice(): ?float
    {
        return $this->original_price ? (float) $this->original_price : null;
    }

    /**
     * Check if the item has a higher original price.
     */
    public function hasAHigherOriginalPrice(): bool
    {
        return $this->original_price && $this->original_price > $this->price;
    }

    /**
     * Add a sale record.
     */
    public function addSale(Carbon $date, int|float $units = 1): void
    {
        $this->increment('units_sold', $units);
        $this->update(['last_sale_at' => $date]);
    }

    /**
     * Remove a sale record.
     */
    public function removeSale(int|float $units = 1): void
    {
        $this->decrement('units_sold', $units);
    }

    /**
     * Get the morph type name for polymorphic relations.
     */
    public function morphTypeName(): string
    {
        return static::class;
    }

    // HasImages interface methods

    /**
     * Check if the product has an image.
     */
    public function hasImage(): bool
    {
        return $this->hasMedia('images') || $this->hasMedia('gallery');
    }

    /**
     * Get the count of images.
     */
    public function imageCount(): int
    {
        return $this->getMedia('images')->count() + $this->getMedia('gallery')->count();
    }

    /**
     * Get the thumbnail URL.
     */
    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_url;
    }

    /**
     * Get all thumbnail URLs.
     */
    public function getThumbnailUrls(): Collection
    {
        $urls = collect();

        $mainImage = $this->getFirstMedia('images');
        if ($mainImage) {
            $urls->push($mainImage->getUrl('thumb'));
        }

        $galleryImages = $this->getMedia('gallery');
        foreach ($galleryImages as $image) {
            $urls->push($image->getUrl('thumb'));
        }

        return $urls;
    }

    /**
     * Get image URL by variant.
     */
    public function getImageUrl(string $variant = ''): ?string
    {
        $media = $this->getFirstMedia('images');
        if (!$media) {
            return null;
        }

        return $variant ? $media->getUrl($variant) : $media->getUrl();
    }

    /**
     * Get all image URLs by variant.
     */
    public function getImageUrls(string $variant = ''): Collection
    {
        $urls = collect();

        $mainImage = $this->getFirstMedia('images');
        if ($mainImage) {
            $urls->push($variant ? $mainImage->getUrl($variant) : $mainImage->getUrl());
        }

        $galleryImages = $this->getMedia('gallery');
        foreach ($galleryImages as $image) {
            $urls->push($variant ? $image->getUrl($variant) : $image->getUrl());
        }

        return $urls;
    }

    /**
     * Сбросить все кэши товаров (используется связанными моделями)
     * Использует оптимизированный метод через теги
     */
    public static function flushAllProductCaches(): void
    {
        // Сбрасываем мета-фильтры категорий (ключи без тегов, поэтому сбрасываем явно)
        $categoryIds = Category::pluck('id');
        foreach ($categoryIds as $categoryId) {
            \Cache::forget('product_filters_meta:cat:' . $categoryId);
        }
        \Cache::forget('product_filters_meta:cat:0');

        // Используем теги для эффективной инвалидации
        if (static::supportsCacheTags()) {
            try {
                \Cache::tags(['product_index_cache', 'product_search_cache', 'product_cache'])->flush();

                // Также сбрасываем все категории товаров
                \Cache::tags(['category_cache'])->flush();
                return;
            } catch (\Exception $e) {
                // Fallback на обычный метод
            }
        }

        // Fallback: сбрасываем базовые кэши
        \Cache::forget(static::cacheKey('index'));
        \Cache::forget(static::cacheKey('featured'));
        \Cache::forget(static::cacheKey('new'));
        \Cache::forget(static::cacheKey('sale'));

        // Сбрасываем кэши категорий
        $categoryModel = Category::class;
        \Cache::forget($categoryModel::cacheKey('index'));
        \Cache::forget($categoryModel::cacheKey('tree'));
    }
}
