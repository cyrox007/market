<?php

declare(strict_types=1);

namespace App\Services\Catalog\Integrations\Ozon;

use App\Actions\Product\SyncVariantVariationAttributesAction;
use App\Jobs\SyncOzonProductImagesJob;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeValue;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class OzonCategoryImportService
{
    /** @var array<string, Attribute> */
    private array $attributeCache = [];

    /** @var array<string, AttributeValue> */
    private array $attributeValueCache = [];

    private const IGNORED_ATTRIBUTE_HEADERS = [
        '№',
        OzonXlsxReader::FIELD_EXTERNAL_ID,
        OzonXlsxReader::FIELD_NAME,
        OzonXlsxReader::FIELD_PRICE,
        OzonXlsxReader::FIELD_ORIGINAL_PRICE,
        OzonXlsxReader::FIELD_ORIGINAL_PRICE_ALT,
        'ндс, %',
        'рассрочка',
        'ускоренный сбор отзывов',
        'sku',
        OzonXlsxReader::FIELD_GTIN,
        'тн вэд коды еаэс',
        OzonXlsxReader::FIELD_PACKAGE_WEIGHT,
        OzonXlsxReader::FIELD_PACKAGE_WIDTH,
        OzonXlsxReader::FIELD_PACKAGE_HEIGHT,
        OzonXlsxReader::FIELD_PACKAGE_LENGTH,
        OzonXlsxReader::FIELD_MAIN_IMAGE,
        OzonXlsxReader::FIELD_GALLERY,
        'артикул фото',
        OzonXlsxReader::FIELD_GROUP,
        OzonXlsxReader::FIELD_COLOR_NAME,
        '#хештеги',
        OzonXlsxReader::FIELD_DESCRIPTION,
        'rich-контент json',
        'объединить в похожие товары',
        OzonXlsxReader::FIELD_TEMPLATE_MODEL_NAME,
        'планирую доставлять товар в нескольких упаковках',
        'ошибка',
        'недочёты',
    ];

    public function __construct(
        private readonly OzonXlsxReader $reader,
        private readonly SyncVariantVariationAttributesAction $syncVariantAttributes,
    ) {
    }

    /**
     * @return array{
     *     total_rows:int,
     *     new_products:int,
     *     existing_products:int,
     *     variable_groups:int,
     *     variable_offers:int,
     *     errors:list<string>
     * }
     */
    public function preview(string $filePath, Category $category): array
    {
        return $this->previewParsed($this->reader->read($filePath), $category);
    }

    /**
     * @param array{rows:list<array<string,mixed>>} $parsed
     * @return array{
     *     total_rows:int,
     *     new_products:int,
     *     existing_products:int,
     *     variable_groups:int,
     *     variable_offers:int,
     *     errors:list<string>
     * }
     */
    private function previewParsed(array $parsed, Category $category): array
    {
        $rows = $parsed['rows'];
        $errors = [];

        $externalIdCounts = array_count_values(array_map(
            static fn (array $row): string => (string) $row['external_id'],
            $rows,
        ));

        foreach ($externalIdCounts as $externalId => $count) {
            if ($count > 1) {
                $errors[] = "Код 1С {$externalId} встречается в файле {$count} раза.";
            }
        }

        $externalIds = array_keys($externalIdCounts);
        $existingProducts = $this->loadProductsByExternalIds($externalIds);
        $dbCounts = $existingProducts->countBy(fn (Product $product): string => (string) $product->external_id);
        foreach ($dbCounts as $externalId => $count) {
            if ($count > 1) {
                $errors[] = "В базе уже есть {$count} товара с кодом 1С {$externalId}. Сначала устраните дубль.";
            }
        }

        $existingUnique = $existingProducts
            ->pluck('external_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->count();

        $groups = $this->buildImportGroups($rows, $category);
        $variableGroups = array_values(array_filter($groups, fn (array $group): bool => $group['kind'] === 'variable'));
        $variableOffers = array_sum(array_map(fn (array $group): int => count($group['rows']), $variableGroups));

        foreach ($variableGroups as $group) {
            $groupExternalIds = array_map(fn (array $row): string => (string) $row['external_id'], $group['rows']);
            $groupProducts = $existingProducts->filter(
                fn (Product $product): bool => in_array((string) $product->external_id, $groupExternalIds, true),
            );

            $parentIds = $groupProducts
                ->pluck('parent_product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($parentIds->count() > 1) {
                $errors[] = 'Группа «' . $group['group_name'] . '» уже разбита между несколькими родительскими товарами.';
                continue;
            }

            foreach ($groupProducts as $product) {
                if (! $product->isVariant() && $product->isVariable() && $product->variants()->exists()) {
                    $errors[] = "Товар с кодом 1С {$product->external_id} уже является вариативным родителем. Автоматически превращать его в торговое предложение небезопасно.";
                }
            }

            $parentByGroupKey = Product::query()
                ->whereNull('parent_product_id')
                ->where('ozon_group_key', $group['group_key'])
                ->first();

            if ($parentByGroupKey !== null && $parentIds->isNotEmpty() && (int) $parentIds->first() !== (int) $parentByGroupKey->id) {
                $errors[] = 'Группа «' . $group['group_name'] . '» конфликтует с ранее созданной Ozon-карточкой.';
            }
        }

        return [
            'total_rows' => count($rows),
            'new_products' => max(0, count($externalIds) - $existingUnique),
            'existing_products' => $existingUnique,
            'variable_groups' => count($variableGroups),
            'variable_offers' => $variableOffers,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array{
     *     total_rows:int,
     *     created_products:int,
     *     updated_products:int,
     *     created_parents:int,
     *     created_variants:int,
     *     updated_variants:int,
     *     images_queued:int,
     *     skipped_rows:int,
     *     errors:list<string>
     * }
     */
    public function import(string $filePath, Category $category): array
    {
        // Большие Ozon XLSX раньше полностью читались дважды: для preview и затем
        // для самого импорта. Читаем файл один раз и проверяем уже разобранные данные.
        $parsed = $this->reader->read($filePath);
        $preview = $this->previewParsed($parsed, $category);
        if ($preview['errors'] !== []) {
            throw new RuntimeException('Импорт остановлен: ' . implode(' ', array_slice($preview['errors'], 0, 5)));
        }

        $headers = $parsed['headers'];
        $groups = $this->buildImportGroups($parsed['rows'], $category);

        $stats = [
            'total_rows' => count($parsed['rows']),
            'created_products' => 0,
            'updated_products' => 0,
            'created_parents' => 0,
            'created_variants' => 0,
            'updated_variants' => 0,
            'images_queued' => 0,
            'skipped_rows' => 0,
            'errors' => [],
        ];

        foreach ($groups as $group) {
            if ($group['kind'] === 'variable') {
                $this->importVariableGroup($group, $headers, $category, $stats);
                continue;
            }

            $this->importSingleRow($group['rows'][0], $headers, $category, $stats);
        }

        Product::flushAllProductCaches();

        return $stats;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $stats
     */
    private function importSingleRow(array $row, array $headers, Category $category, array &$stats): void
    {
        DB::transaction(function () use ($row, $headers, $category, &$stats): void {
            $product = Product::query()->where('external_id', (string) $row['external_id'])->first();
            $isNew = $product === null;

            if ($product === null) {
                $product = new Product;
                $product->state = Product::ACTIVE;
                $product->stock = 0;
                $product->backorder = false;
                $product->units_sold = 0;
                $product->is_variable = false;
            }

            $this->applyRowData($product, $row, $isNew);
            $product->save();
            $product->taxons()->syncWithoutDetaching([$category->id]);

            if ($product->isVariant() && $product->parentProduct) {
                $product->parentProduct->taxons()->syncWithoutDetaching([$category->id]);
            }

            $this->syncProductAttributes($product, $row, $headers, $product->isVariant());

            if ($isNew) {
                $stats['created_products']++;
            } else {
                $stats['updated_products']++;
            }

            if ($this->queueImages($product, $row)) {
                $stats['images_queued']++;
            }
        });
    }

    /**
     * @param array{kind:string,group_name:string,group_key:string,rows:list<array<string,mixed>>} $group
     * @param array<string, string> $headers
     * @param array<string, mixed> $stats
     */
    private function importVariableGroup(array $group, array $headers, Category $category, array &$stats): void
    {
        DB::transaction(function () use ($group, $headers, $category, &$stats): void {
            $externalIds = array_map(fn (array $row): string => (string) $row['external_id'], $group['rows']);
            $existing = $this->loadProductsByExternalIds($externalIds);

            $parentIds = $existing
                ->pluck('parent_product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($parentIds->count() > 1) {
                throw new RuntimeException('Группа «' . $group['group_name'] . '» связана с несколькими родительскими товарами.');
            }

            $parent = Product::query()
                ->whereNull('parent_product_id')
                ->where('ozon_group_key', $group['group_key'])
                ->first();

            if ($parentIds->isNotEmpty()) {
                $linkedParent = Product::query()->find((int) $parentIds->first());
                if ($linkedParent === null) {
                    throw new RuntimeException('Не найден родитель существующего торгового предложения Ozon.');
                }
                if ($parent !== null && $parent->id !== $linkedParent->id) {
                    throw new RuntimeException('Конфликт родительских карточек для группы «' . $group['group_name'] . '».');
                }
                $parent = $linkedParent;
            }

            if ($parent === null) {
                $parent = $this->createVariableParent($group, $category);
                $stats['created_parents']++;
            } else {
                // Тот же товар может быть прикреплён к нескольким категориям. Если родитель
                // уже найден через существующие торговые предложения, сохраняем его исходный
                // технический ключ вместо создания/перепривязки второй карточки.
                $parent->forceFill([
                    'ozon_group_key' => $parent->ozon_group_key ?: $group['group_key'],
                    'is_variable' => true,
                    'parent_product_id' => null,
                    'external_id' => null,
                    'sku' => null,
                ])->save();
            }

            $parent->taxons()->syncWithoutDetaching([$category->id]);
            $this->syncCommonParentAttributes($parent, $group['rows'], $headers);

            $labels = $this->resolveVariantLabels($group['rows']);
            foreach ($group['rows'] as $row) {
                $product = $existing->first(
                    fn (Product $candidate): bool => (string) $candidate->external_id === (string) $row['external_id'],
                );
                $isNew = $product === null;

                if ($product === null) {
                    $product = new Product;
                    $product->state = Product::ACTIVE;
                    $product->stock = 0;
                    $product->backorder = false;
                    $product->units_sold = 0;
                } elseif (! $product->isVariant() && $product->isVariable() && $product->variants()->exists()) {
                    throw new RuntimeException(
                        "Товар {$product->external_id} уже является вариативным родителем и не может быть автоматически перенесён в другую карточку.",
                    );
                }

                $product->parent_product_id = $parent->id;
                $product->is_variable = false;
                $this->applyRowData($product, $row, $isNew);
                $product->save();
                $product->taxons()->syncWithoutDetaching([$category->id]);

                $this->syncProductAttributes($product, $row, $headers, true);
                $this->syncVariationAttributes(
                    $parent,
                    $product,
                    $row,
                    $headers,
                    $category,
                    $labels[(string) $row['external_id']],
                );

                if ($isNew) {
                    $stats['created_products']++;
                    $stats['created_variants']++;
                } else {
                    $stats['updated_products']++;
                    $stats['updated_variants']++;
                }

                if ($this->queueImages($product, $row)) {
                    $stats['images_queued']++;
                }
            }

            Product::syncParentPriceFromVariants($parent->fresh());

            $representative = $group['rows'][0] ?? null;
            if ($representative !== null && $this->queueImages($parent, [
                'main_image_url' => $representative['main_image_url'] ?? null,
                'gallery_urls' => [],
            ])) {
                $stats['images_queued']++;
            }
        });
    }

    /** @param array<string, mixed> $group */
    private function createVariableParent(array $group, Category $category): Product
    {
        $name = $this->resolveParentName($group['rows'], $group['group_name']);
        $prices = array_values(array_filter(array_map(
            fn (array $row): ?float => $this->decimal($row['price'] ?? null),
            $group['rows'],
        ), fn (?float $value): bool => $value !== null));

        $parent = new Product;
        $parent->name = $name;
        $parent->slug = $this->uniqueSlug($name, 'ozon-' . substr($group['group_key'], 0, 10));
        $parent->external_id = null;
        $parent->ozon_group_key = $group['group_key'];
        $parent->sku = null;
        $parent->is_variable = true;
        $parent->parent_product_id = null;
        $parent->state = Product::ACTIVE;
        $parent->price = $prices !== [] ? min($prices) : 0;
        $parent->stock = 0;
        $parent->backorder = false;
        $parent->units_sold = 0;

        $description = collect($group['rows'])
            ->pluck('description')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->first();
        if ($description !== null) {
            $parent->description = (string) $description;
        }

        $parent->save();
        $parent->taxons()->syncWithoutDetaching([$category->id]);

        return $parent;
    }

    private function applyRowData(Product $product, array $row, bool $isNew): void
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name !== '') {
            $product->name = $name;
            if ($isNew || trim((string) ($product->slug ?? '')) === '') {
                $product->slug = $this->uniqueSlug($name, (string) $row['external_id'], $product->id);
            }
        }

        $product->external_id = (string) $row['external_id'];

        $price = $this->decimal($row['price'] ?? null);
        if ($price !== null) {
            $product->price = $price;
        }

        $originalPrice = $this->decimal($row['original_price'] ?? null);
        if ($originalPrice !== null) {
            $product->original_price = $originalPrice;
        }

        $gtin = trim((string) ($row['gtin'] ?? ''));
        if ($gtin !== '') {
            $product->gtin = $gtin;
        }

        $description = trim((string) ($row['description'] ?? ''));
        if ($description !== '') {
            $product->description = $description;
        }

        $weight = $this->decimal($row['package_weight_g'] ?? null);
        if ($weight !== null && $weight > 0) {
            $product->weight = $weight / 1000;
        }
        $width = $this->decimal($row['package_width_mm'] ?? null);
        if ($width !== null && $width > 0) {
            $product->width = $width / 10;
        }
        $height = $this->decimal($row['package_height_mm'] ?? null);
        if ($height !== null && $height > 0) {
            $product->height = $height / 10;
        }
        $length = $this->decimal($row['package_length_mm'] ?? null);
        if ($length !== null && $length > 0) {
            $product->length = $length / 10;
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function syncProductAttributes(Product $product, array $row, array $headers, bool $isVariant): void
    {
        foreach (($row['fields'] ?? []) as $normalizedHeader => $rawValue) {
            $normalizedHeader = (string) $normalizedHeader;
            if (! $this->shouldImportAsAttribute($normalizedHeader, $rawValue)) {
                continue;
            }
            if ($isVariant && in_array($normalizedHeader, [OzonXlsxReader::FIELD_COLOR, OzonXlsxReader::FIELD_COLOR_NAME], true)) {
                continue;
            }

            $displayName = $headers[$normalizedHeader] ?? $normalizedHeader;
            $attribute = $this->resolveAttribute($normalizedHeader, $displayName);
            $this->replaceProductAttribute($product, $attribute, $rawValue);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $headers
     */
    private function syncCommonParentAttributes(Product $parent, array $rows, array $headers): void
    {
        if ($rows === []) {
            return;
        }

        $candidateHeaders = array_keys($rows[0]['fields'] ?? []);
        foreach ($candidateHeaders as $normalizedHeader) {
            if (in_array($normalizedHeader, [OzonXlsxReader::FIELD_COLOR, OzonXlsxReader::FIELD_COLOR_NAME], true)) {
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $value = $row['fields'][$normalizedHeader] ?? null;
                if (! $this->shouldImportAsAttribute((string) $normalizedHeader, $value)) {
                    $values = [];
                    break;
                }
                $values[] = trim((string) $value);
            }

            if ($values === [] || count(array_unique(array_map('mb_strtolower', $values))) !== 1) {
                continue;
            }

            $displayName = $headers[$normalizedHeader] ?? (string) $normalizedHeader;
            $attribute = $this->resolveAttribute((string) $normalizedHeader, $displayName);
            $this->replaceProductAttribute($parent, $attribute, $values[0]);
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function syncVariationAttributes(
        Product $parent,
        Product $variant,
        array $row,
        array $headers,
        Category $category,
        string $variantLabel,
    ): void {
        $variantAttribute = Attribute::ensureVariantAttribute();
        $formData = ['variation_custom_' . $variantAttribute->id => $variantLabel];
        $attributeIds = [(int) $variantAttribute->id];

        $colorRaw = trim((string) ($row['color'] ?? ''));
        if ($colorRaw !== '') {
            $colorAttribute = $this->ensureColorVariationAttribute();
            $valueIds = [];
            foreach ($this->splitMultipleValues($colorRaw) as $value) {
                $valueIds[] = $this->resolveAttributeValue($colorAttribute, $value)->id;
            }
            if ($valueIds !== []) {
                $formData['variation_attr_' . $colorAttribute->id] = $valueIds;
                $attributeIds[] = (int) $colorAttribute->id;
            }
        }

        foreach ($category->variationAttributes()->get() as $attribute) {
            if (in_array((int) $attribute->id, $attributeIds, true)) {
                continue;
            }

            $sourceValue = $this->findValueForAttribute($attribute, $row, $headers);
            if ($sourceValue === null || trim((string) $sourceValue) === '') {
                continue;
            }

            $this->putVariationValue($formData, $attribute, $sourceValue);
            $attributeIds[] = (int) $attribute->id;
        }

        $attributeIds = array_values(array_unique($attributeIds));
        $parent->variationAttributeSelection()->syncWithoutDetaching($attributeIds);
        $this->syncVariantAttributes->execute($variant, $formData, $parent, $attributeIds);
    }

    private function putVariationValue(array &$formData, Attribute $attribute, mixed $rawValue): void
    {
        $value = trim((string) $rawValue);
        if ($value === '') {
            return;
        }

        if ($attribute->type === 'select' || $attribute->type === 'color' || ! $attribute->allow_custom_value) {
            $values = $this->splitMultipleValues($value);
            $ids = array_map(
                fn (string $part): int => (int) $this->resolveAttributeValue($attribute, $part)->id,
                $values,
            );
            $formData['variation_attr_' . $attribute->id] = $attribute->is_multiple ? $ids : ($ids[0] ?? null);
            return;
        }

        $formData['variation_custom_' . $attribute->id] = $value;
    }

    private function findValueForAttribute(Attribute $attribute, array $row, array $headers): mixed
    {
        foreach (($row['fields'] ?? []) as $normalizedHeader => $value) {
            $displayName = $headers[$normalizedHeader] ?? $normalizedHeader;
            $candidateSlug = Attribute::canonicalSlugForName($this->cleanAttributeName((string) $displayName));
            if ($candidateSlug === $attribute->slug || Str::slug($this->cleanAttributeName((string) $displayName)) === $attribute->slug) {
                return $value;
            }
        }

        return null;
    }

    private function ensureColorVariationAttribute(): Attribute
    {
        $attribute = Attribute::query()->firstOrCreate(
            ['slug' => Attribute::SLUG_COLOR],
            [
                'name' => 'Цвет',
                'type' => 'color',
                'is_filterable' => true,
                'is_use_in_variations' => true,
                'allow_custom_value' => false,
                'is_multiple' => true,
                'sort_order' => 10,
            ],
        );

        $changes = [];
        if (! $attribute->is_use_in_variations) {
            $changes['is_use_in_variations'] = true;
        }
        if (! $attribute->is_multiple) {
            $changes['is_multiple'] = true;
        }
        if ($attribute->type !== 'color') {
            $changes['type'] = 'color';
        }
        if ($changes !== []) {
            $attribute->forceFill($changes)->saveQuietly();
        }

        $this->attributeCache[Attribute::SLUG_COLOR] = $attribute;

        return $attribute;
    }

    private function resolveAttribute(string $normalizedHeader, string $displayName): Attribute
    {
        $name = $this->cleanAttributeName($displayName);
        $slug = $normalizedHeader === OzonXlsxReader::FIELD_COLOR
            ? Attribute::SLUG_COLOR
            : Attribute::canonicalSlugForName($name);
        if ($slug === '') {
            $slug = 'ozon-' . substr(sha1(mb_strtolower($name)), 0, 16);
        }

        if (isset($this->attributeCache[$slug])) {
            return $this->attributeCache[$slug];
        }

        $defaults = [
            'name' => $name,
            'type' => $slug === Attribute::SLUG_COLOR ? 'color' : 'text',
            'is_filterable' => $slug === Attribute::SLUG_COLOR,
            'is_required' => false,
            'is_use_in_variations' => false,
            'allow_custom_value' => $slug !== Attribute::SLUG_COLOR,
            'is_multiple' => $slug === Attribute::SLUG_COLOR,
            'sort_order' => 100,
        ];

        $attribute = Attribute::query()->where('slug', $slug)->first();
        if ($attribute === null) {
            $attribute = Attribute::query()
                ->where('name', $name)
                ->first();
        }
        if ($attribute === null) {
            $attribute = Attribute::query()->create(array_merge(['slug' => $slug], $defaults));
        }

        if ($slug === Attribute::SLUG_COLOR) {
            $changes = [];
            if ($attribute->type !== 'color') {
                $changes['type'] = 'color';
            }
            if (! $attribute->is_multiple) {
                $changes['is_multiple'] = true;
            }
            if ($changes !== []) {
                $attribute->forceFill($changes)->saveQuietly();
            }
        }

        $this->attributeCache[$slug] = $attribute;

        return $attribute;
    }

    private function replaceProductAttribute(Product $product, Attribute $attribute, mixed $rawValue): void
    {
        $value = trim((string) $rawValue);
        if ($value === '') {
            return;
        }

        DB::table('product_product_attributes')
            ->where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->delete();

        $now = now();
        $useValues = $attribute->type === 'select'
            || $attribute->type === 'color'
            || ! $attribute->allow_custom_value;

        if ($useValues) {
            $parts = $attribute->is_multiple ? $this->splitMultipleValues($value) : [$value];
            foreach ($parts as $part) {
                $attributeValue = $this->resolveAttributeValue($attribute, $part);
                DB::table('product_product_attributes')->insert([
                    'product_id' => $product->id,
                    'attribute_id' => $attribute->id,
                    'attribute_value_id' => $attributeValue->id,
                    'custom_value' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            return;
        }

        DB::table('product_product_attributes')->insert([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'attribute_value_id' => null,
            'custom_value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function resolveAttributeValue(Attribute $attribute, string $value): AttributeValue
    {
        $value = trim($value);
        $slug = Str::slug($value);
        if ($slug === '') {
            $slug = 'value-' . substr(sha1(mb_strtolower($value)), 0, 16);
        }

        $cacheKey = $attribute->id . ':' . $slug;
        if (isset($this->attributeValueCache[$cacheKey])) {
            return $this->attributeValueCache[$cacheKey];
        }

        $attributeValue = AttributeValue::query()->firstOrCreate(
            ['attribute_id' => $attribute->id, 'slug' => $slug],
            ['value' => $value, 'sort_order' => 0],
        );
        $this->attributeValueCache[$cacheKey] = $attributeValue;

        return $attributeValue;
    }

    private function shouldImportAsAttribute(string $normalizedHeader, mixed $value): bool
    {
        if (in_array($normalizedHeader, self::IGNORED_ATTRIBUTE_HEADERS, true)) {
            return false;
        }

        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    /** @return list<string> */
    private function splitMultipleValues(string $value): array
    {
        $parts = preg_split('/\s*;\s*/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('trim', $parts)));
    }

    private function cleanAttributeName(string $name): string
    {
        $name = trim(str_replace("\u{00A0}", ' ', $name));
        $name = preg_replace('/\*+$/u', '', $name) ?? $name;

        return trim($name);
    }

    private function queueImages(Product $product, array $row): bool
    {
        $main = trim((string) ($row['main_image_url'] ?? ''));
        $gallery = array_values(array_filter(array_map(
            static fn ($url): string => trim((string) $url),
            $row['gallery_urls'] ?? [],
        )));

        if ($main === '' && $gallery === []) {
            return false;
        }

        SyncOzonProductImagesJob::dispatch(
            (int) $product->id,
            $main !== '' ? $main : null,
            $gallery,
        )->afterCommit();

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, string> external_id => label
     */
    private function resolveVariantLabels(array $rows): array
    {
        $labels = [];
        $counts = [];

        foreach ($rows as $row) {
            $candidate = trim((string) ($row['color_name'] ?? ''));
            if ($candidate === '') {
                $candidate = trim((string) ($row['color'] ?? ''));
            }
            if ($candidate === '') {
                $candidate = trim((string) ($row['name'] ?? ''));
            }
            if ($candidate === '') {
                $candidate = 'Вариант ' . $row['external_id'];
            }

            $key = mb_strtolower($candidate);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $labels[(string) $row['external_id']] = $candidate;
        }

        foreach ($rows as $row) {
            $externalId = (string) $row['external_id'];
            $candidate = $labels[$externalId];
            if (($counts[mb_strtolower($candidate)] ?? 0) <= 1) {
                continue;
            }

            $productName = trim((string) ($row['name'] ?? ''));
            $labels[$externalId] = $productName !== ''
                ? $productName
                : $candidate . ' · ' . $externalId;
        }

        $finalCounts = array_count_values(array_map(
            static fn (string $label): string => mb_strtolower($label),
            $labels,
        ));
        foreach ($labels as $externalId => $label) {
            if (($finalCounts[mb_strtolower($label)] ?? 0) > 1) {
                $labels[$externalId] = $label . ' · ' . $externalId;
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function resolveParentName(array $rows, string $groupName): string
    {
        $templateNames = collect($rows)
            ->pluck('template_model_name')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();
        if ($templateNames->count() === 1 && mb_strlen((string) $templateNames->first()) >= 4) {
            return (string) $templateNames->first();
        }

        $names = array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $rows,
        )));
        $common = $this->longestCommonWordPrefix($names);
        if (mb_strlen($common) >= 20 && count(preg_split('/\s+/u', $common) ?: []) >= 3) {
            return $common;
        }

        $groupName = trim($groupName, " \t\n\r\0\x0B,.;_-\"");
        if ($groupName !== '') {
            return $groupName;
        }

        return $names[0] ?? 'Товар Ozon';
    }

    /** @param list<string> $names */
    private function longestCommonWordPrefix(array $names): string
    {
        if ($names === []) {
            return '';
        }

        $base = preg_split('/\s+/u', trim($names[0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $limit = count($base);

        foreach (array_slice($names, 1) as $name) {
            $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $newLimit = 0;
            for ($i = 0; $i < min($limit, count($words)); $i++) {
                if (mb_strtolower($base[$i]) !== mb_strtolower($words[$i])) {
                    break;
                }
                $newLimit++;
            }
            $limit = $newLimit;
            if ($limit === 0) {
                break;
            }
        }

        return trim(implode(' ', array_slice($base, 0, $limit)), " \t\n\r\0\x0B,.;:_-");
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{kind:string,group_name:string,group_key:string,rows:list<array<string,mixed>>}>
     */
    private function buildImportGroups(array $rows, Category $category): array
    {
        $groupCounts = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeGroupName((string) ($row['group_name'] ?? ''));
            if ($normalized !== '') {
                $groupCounts[$normalized] = ($groupCounts[$normalized] ?? 0) + 1;
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $groupName = trim((string) ($row['group_name'] ?? ''));
            $normalized = $this->normalizeGroupName($groupName);
            $isVariable = $normalized !== '' && ($groupCounts[$normalized] ?? 0) >= 2;
            $bucketKey = $isVariable ? 'group:' . $normalized : 'single:' . $row['external_id'];

            if (! isset($groups[$bucketKey])) {
                $groups[$bucketKey] = [
                    'kind' => $isVariable ? 'variable' : 'single',
                    'group_name' => $isVariable ? $groupName : '',
                    'group_key' => $isVariable
                        ? hash('sha256', $category->id . '|' . $normalized)
                        : '',
                    'rows' => [],
                ];
            }
            $groups[$bucketKey]['rows'][] = $row;
        }

        return array_values($groups);
    }

    private function normalizeGroupName(string $name): string
    {
        $name = str_replace("\u{00A0}", ' ', trim($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name);
    }

    /** @param list<string> $externalIds */
    private function loadProductsByExternalIds(array $externalIds): Collection
    {
        $products = collect();
        foreach (array_chunk(array_values(array_unique($externalIds)), 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $products = $products->concat(
                Product::query()->whereIn('external_id', $chunk)->get(),
            );
        }

        return $products->values();
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace(["\u{00A0}", ' ', ','], ['', '', '.'], trim((string) $value));
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? $normalized;

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function uniqueSlug(string $name, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = Str::slug($fallback);
        }
        if ($base === '') {
            $base = 'ozon-product';
        }

        $slug = $base;
        $suffix = 2;
        while (Product::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
