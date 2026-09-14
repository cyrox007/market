from pathlib import Path
import re


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise RuntimeError(f"missing replacement target: {label}")
    if text.count(old) != 1:
        raise RuntimeError(f"replacement target is not unique ({text.count(old)}): {label}")
    return text.replace(old, new, 1)


def regex_once(text: str, pattern: str, replacement: str, label: str) -> str:
    result, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"regex replacement count {count}: {label}")
    return result


# ProductController: canonical color/size filtering and filter metadata.
path = Path('services/backend/app/Http/Controllers/Api/ProductController.php')
text = path.read_text()
text = replace_once(
    text,
    "use App\\Services\\Product\\ProductRegionRuleService;\n",
    "use App\\Services\\Product\\ProductRegionRuleService;\nuse App\\Services\\Product\\ProductCanonicalAttributeQueryService;\n",
    'ProductController import',
)

canonical_filter_block = '''        // Канонические цвет и коммерческий размер хранятся как характеристики.
        // Старые products.color/color_code и физические length/width/height здесь больше не участвуют.
        $canonicalAttributeQuery = app(ProductCanonicalAttributeQueryService::class);

        $colors = array_values(array_filter(array_map('trim', $colors)));
        $canonicalColors = array_values(array_unique(array_merge(
            $colors,
            (array) ($attributes[Attribute::SLUG_COLOR] ?? []),
        )));
        if ($canonicalColors !== []) {
            $query = $canonicalAttributeQuery->applyFilter($query, Attribute::SLUG_COLOR, $canonicalColors);
        }

        $sizes = array_values(array_filter(array_map('trim', $sizes)));
        $canonicalSizes = array_values(array_unique(array_merge(
            $sizes,
            (array) ($attributes[Attribute::SLUG_SIZE] ?? []),
        )));
        if ($canonicalSizes !== []) {
            $query = $canonicalAttributeQuery->applyFilter($query, Attribute::SLUG_SIZE, $canonicalSizes);
        }

        // Эти два системных атрибута уже применены единым каноническим фильтром выше.
        unset($attributes[Attribute::SLUG_COLOR], $attributes[Attribute::SLUG_SIZE]);

        // Фильтрация по характеристикам:'''
text = regex_once(
    text,
    r"        // Фильтр по цвету \(цвет в самом товаре или в его вариациях\).*?        // Фильтрация по характеристикам:",
    canonical_filter_block,
    'ProductController legacy color/size filters',
)

meta_block = '''        $canonicalAttributeQuery = app(ProductCanonicalAttributeQueryService::class);

        $colors = $canonicalAttributeQuery
            ->buildFilterMeta(clone $queryForFilters, Attribute::SLUG_COLOR)
            ->map(fn (array $value) => [
                'id' => $value['id'],
                'name' => $value['name'],
                'slug' => $value['slug'],
                'code' => $value['code'],
                'count' => $value['count'],
            ]);

        // Коммерческие размеры — только характеристика size. Габариты доставки сюда не попадают.
        $sizes = $canonicalAttributeQuery
            ->buildFilterMeta(clone $queryForFilters, Attribute::SLUG_SIZE)
            ->map(fn (array $value) => [
                'id' => $value['id'],
                'name' => $value['name'],
                'slug' => $value['slug'],
                'value' => $value['value'],
                'count' => $value['count'],
            ]);

        // ID товаров и вариаций подзапросом для атрибутов (клонируем для каждого использования)'''
text = regex_once(
    text,
    r"        // Цвета из товаров и их вариаций \(подзапросы вместо whereIn\(массив ID\)\).*?        // ID товаров и вариаций подзапросом для атрибутов \(клонируем для каждого использования\)",
    meta_block,
    'ProductController filter meta',
)

text = replace_once(
    text,
    "            ->where('product_attributes.is_filterable', true)\n            ->whereIn('product_product_attributes.product_id', $productOrVariantIdsSubquery())",
    "            ->where('product_attributes.is_filterable', true)\n            ->whereNotIn('product_attributes.slug', [Attribute::SLUG_COLOR, Attribute::SLUG_SIZE])\n            ->whereIn('product_product_attributes.product_id', $productOrVariantIdsSubquery())",
    'ProductController avoid duplicate canonical filter metadata',
)

text = replace_once(
    text,
    'description="Размер вариации в формате lengthxwidth (например 280x180)"',
    'description="Коммерческий размер вариации: значение или slug характеристики size (не физические габариты товара)"',
    'ProductController OpenAPI size description',
)

text = replace_once(
    text,
    "            if ($product->isVariable() && $attributesParam !== []) {\n                $variant = $product->getVariantByVariationAttributes($attributesParam);",
    "            if ($product->isVariable() && $attributesParam !== []) {\n                $variationSelection = [];\n                foreach ($attributesParam as $attributeSlug => $rawValue) {\n                    $candidates = is_array($rawValue) ? $rawValue : [$rawValue];\n                    foreach ($candidates as $candidate) {\n                        $candidate = trim((string) $candidate);\n                        if ($candidate !== '') {\n                            $variationSelection[$attributeSlug] = $candidate;\n                            break;\n                        }\n                    }\n                }\n\n                $variant = $product->getVariantByVariationAttributes($variationSelection);",
    'ProductController normalize variant selection arrays',
)
path.write_text(text)


# Product model: keep legacy method names only as compatibility wrappers over canonical attributes.
path = Path('services/backend/app/Models/Product/Product.php')
text = path.read_text()
text = replace_once(
    text,
    "use App\\Models\\Product\\Review;\n",
    "use App\\Models\\Product\\Review;\nuse App\\Services\\Product\\ProductCanonicalAttributeQueryService;\nuse App\\Services\\Product\\ProductVariationAttributeService;\n",
    'Product service imports',
)

text = regex_once(
    text,
    r"    /\*\*\n     \* Получить доступные цвета для вариаций.*?(?=    /\*\*\n     \* Получить доступные размеры для вариаций)",
    '''    /**
     * Совместимый API: доступные цвета читаются только из канонической характеристики color.
     */
    public function getAvailableColors(): Collection
    {
        return app(ProductVariationAttributeService::class)->colors($this);
    }

''',
    'Product getAvailableColors',
)
text = regex_once(
    text,
    r"    /\*\*\n     \* Получить доступные размеры для вариаций.*?(?=    /\*\*\n     \* Получить вариацию по цвету и размеру)",
    '''    /**
     * Совместимый API: коммерческие размеры читаются только из характеристики size.
     * Физические length/width/height используются исключительно как габариты доставки.
     */
    public function getAvailableSizes(): Collection
    {
        return app(ProductVariationAttributeService::class)->sizes($this);
    }

''',
    'Product getAvailableSizes',
)
text = regex_once(
    text,
    r"    /\*\*\n     \* Получить вариацию по цвету и размеру.*?(?=    /\*\*\n     \* Получить доступные размеры для конкретного цвета)",
    '''    /**
     * Совместимый API выбора вариации по каноническим color/size.
     */
    public function getVariantByAttributes(?string $color = null, ?string $size = null): ?Product
    {
        $attributes = [];
        if ($color !== null && trim($color) !== '') {
            $attributes[Attribute::SLUG_COLOR] = trim($color);
        }
        if ($size !== null && trim($size) !== '') {
            $attributes[Attribute::SLUG_SIZE] = trim($size);
        }

        if ($attributes === []) {
            return $this->isVariable() ? null : $this;
        }

        return $this->getVariantByVariationAttributes($attributes);
    }

''',
    'Product getVariantByAttributes',
)
text = regex_once(
    text,
    r"    /\*\*\n     \* Получить доступные размеры для конкретного цвета.*?(?=    /\*\*\n     \* Получить доступные цвета для конкретного размера)",
    '''    /**
     * Доступные коммерческие размеры для выбранного канонического цвета.
     */
    public function getAvailableSizesForColor(?string $color = null): Collection
    {
        $constraints = $color !== null && trim($color) !== ''
            ? [Attribute::SLUG_COLOR => trim($color)]
            : [];

        return app(ProductCanonicalAttributeQueryService::class)
            ->availableVariationValues($this, Attribute::SLUG_SIZE, $constraints);
    }

''',
    'Product getAvailableSizesForColor',
)
text = regex_once(
    text,
    r"    /\*\*\n     \* Получить доступные цвета для конкретного размера.*?(?=    /\*\*\n     \* Get main category)",
    '''    /**
     * Доступные канонические цвета для выбранного коммерческого размера.
     */
    public function getAvailableColorsForSize(?string $size = null): Collection
    {
        $constraints = $size !== null && trim($size) !== ''
            ? [Attribute::SLUG_SIZE => trim($size)]
            : [];

        return app(ProductCanonicalAttributeQueryService::class)
            ->availableVariationValues($this, Attribute::SLUG_COLOR, $constraints);
    }

''',
    'Product getAvailableColorsForSize',
)
path.write_text(text)


# Operator UI: remove dedicated legacy color fields and inline schema creation.
path = Path('services/backend/app/Filament/Resources/Products/Schemas/ProductTabbedForm.php')
text = path.read_text()
text = replace_once(
    text,
    "use App\\Models\\Product\\Product;\n",
    "use App\\Models\\Product\\Attribute;\nuse App\\Models\\Product\\AttributeValue;\nuse App\\Models\\Product\\Product;\n",
    'ProductTabbedForm model imports',
)
text = replace_once(
    text,
    "use Filament\\Forms\\Components\\Placeholder;\n",
    "use Filament\\Forms\\Components\\Placeholder;\nuse Filament\\Forms\\Components\\Repeater;\n",
    'ProductTabbedForm repeater import',
)
text = replace_once(
    text,
    "                                self::colorSection()->collapsed(false),\n                                self::attributesSection()->collapsed(false),",
    "                                self::operatorAttributesSection(),",
    'ProductTabbedForm characteristic tab',
)

operator_method = '''

    /**
     * Оператор выбирает только уже заведённые характеристики и значения.
     * Структуру справочника (тип, фильтры, участие в вариациях) меняют в отдельном разделе «Характеристики».
     */
    protected static function operatorAttributesSection(): Section
    {
        return Section::make('Характеристики товара')
            ->description('Цвет, коммерческий размер, материал и другие свойства. Для вариативного товара цвет/размер задаются в его вариациях.')
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Характеристики')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Характеристика')
                            ->options(function (?Product $record) {
                                $query = Attribute::query()->orderBy('name');
                                if ($record?->is_variable) {
                                    $query->where('is_use_in_variations', false);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($set) {
                                $set('attribute_value_id', null);
                                $set('custom_value', null);
                            }),

                        Select::make('attribute_value_id')
                            ->label('Значение')
                            ->options(function ($get) {
                                $attributeId = $get('attribute_id');
                                if (! $attributeId) {
                                    return [];
                                }

                                return AttributeValue::query()
                                    ->where('attribute_id', $attributeId)
                                    ->orderBy('sort_order')
                                    ->orderBy('value')
                                    ->pluck('value', 'id');
                            })
                            ->multiple(function ($get) {
                                $attributeId = $get('attribute_id');
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->is_multiple;
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn ($get) => ! $get('attribute_id'))
                            ->afterStateUpdated(function ($state, $set) {
                                if (! empty($state)) {
                                    $set('custom_value', null);
                                }
                            }),

                        TextInput::make('custom_value')
                            ->label('Ручное значение')
                            ->maxLength(500)
                            ->visible(function ($get) {
                                $attributeId = $get('attribute_id');
                                return $attributeId && (bool) Attribute::query()->find($attributeId)?->allow_custom_value;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state !== null && $state !== '') {
                                    $set('attribute_value_id', null);
                                }
                            }),
                    ])
                    ->columns(1)
                    ->compact()
                    ->defaultItems(0)
                    ->addActionLabel('Добавить характеристику')
                    ->collapsible()
                    ->itemLabel(function (array $state): string {
                        $attribute = Attribute::query()->find($state['attribute_id'] ?? null);
                        if (! $attribute) {
                            return 'Характеристика';
                        }

                        $valueId = $state['attribute_value_id'] ?? null;
                        if (is_array($valueId)) {
                            return $attribute->name . ' (несколько значений)';
                        }
                        if ($valueId) {
                            $value = AttributeValue::query()->find($valueId);
                            return $attribute->name . ($value ? ': ' . $value->value : '');
                        }
                        if (! empty($state['custom_value'])) {
                            return $attribute->name . ': ' . $state['custom_value'];
                        }

                        return $attribute->name;
                    })
                    ->helperText('Если нужной характеристики или значения нет, добавьте их в разделе «Характеристики» в меню — здесь структура справочника не создаётся.')
                    ->columnSpanFull(),
            ]);
    }
'''
if 'protected static function operatorAttributesSection()' not in text:
    idx = text.rfind('\n}')
    if idx == -1:
        raise RuntimeError('ProductTabbedForm closing brace not found')
    text = text[:idx] + operator_method + text[idx:]
path.write_text(text)


# Make physical dimensions unambiguous for the operator.
path = Path('services/backend/app/Filament/Resources/Products/Schemas/ProductForm.php')
text = path.read_text()
text = replace_once(
    text,
    "return Section::make('Размеры и вес')\n            ->description('Физические параметры товара для расчета доставки')",
    "return Section::make('Габариты и вес для доставки')\n            ->description('Физические параметры упаковки/товара для расчёта доставки. Это не коммерческий размер вариации.')",
    'ProductForm dimensions label',
)
path.write_text(text)


# Persist product attributes on create through the same service used by edit.
path = Path('services/backend/app/Filament/Resources/Products/Pages/CreateProduct.php')
text = path.read_text()
text = replace_once(
    text,
    "use App\\Services\\Catalog\\OneCProductSyncService;\n",
    "use App\\Services\\Catalog\\OneCProductSyncService;\nuse App\\Services\\Product\\ProductAttributeSyncService;\n",
    'CreateProduct sync service import',
)
text = replace_once(
    text,
    "    protected bool $exitAfterSave = false;\n",
    "    protected bool $exitAfterSave = false;\n\n    protected array $productAttributesData = [];\n",
    'CreateProduct attribute state',
)
text = replace_once(
    text,
    "    protected function mutateFormDataBeforeCreate(array $data): array\n    {\n        if (!array_key_exists('sku', $data) || $data['sku'] === null) {",
    "    protected function mutateFormDataBeforeCreate(array $data): array\n    {\n        $this->productAttributesData = $data['product_attributes'] ?? [];\n        unset($data['product_attributes']);\n\n        if (!array_key_exists('sku', $data) || $data['sku'] === null) {",
    'CreateProduct extract attributes',
)
text = replace_once(
    text,
    "    protected function afterCreate(): void\n    {\n        $this->record->flushCache();",
    "    protected function afterCreate(): void\n    {\n        app(ProductAttributeSyncService::class)->sync($this->record, $this->productAttributesData);\n\n        $this->record->flushCache();",
    'CreateProduct persist attributes',
)
path.write_text(text)


# Reuse shared persistence service on edit.
path = Path('services/backend/app/Filament/Resources/Products/Pages/EditProduct.php')
text = path.read_text()
text = text.replace("use App\\Models\\Product\\AttributeValue;\n", '')
text = text.replace("use Illuminate\\Support\\Facades\\DB;\n", '')
text = replace_once(
    text,
    "use App\\Services\\Catalog\\OneCProductSyncService;\n",
    "use App\\Services\\Catalog\\OneCProductSyncService;\nuse App\\Services\\Product\\ProductAttributeSyncService;\n",
    'EditProduct sync service import',
)
text = regex_once(
    text,
    r"            // Обрабатываем характеристики товара \(и для обычных, и для вариативных — у последних это производитель и др\.\)\n            DB::transaction\(function \(\) use \(\$product\) \{.*?\n            \}\);",
    "            app(ProductAttributeSyncService::class)->sync($product, $this->productAttributesData);",
    'EditProduct shared attribute persistence',
)
path.write_text(text)


# Canonical slugs for future imports from 1C, so Цвет/Размер do not re-create tsvet/razmer duplicates.
path = Path('services/backend/app/Models/Product/Attribute.php')
text = path.read_text()
text = replace_once(
    text,
    "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n",
    "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\nuse Illuminate\\Support\\Str;\n",
    'Attribute Str import',
)
anchor = "    public const SLUG_VARIANT = 'variant';\n"
method = '''    public const SLUG_VARIANT = 'variant';

    /**
     * Нормализовать имена системных характеристик из внешних источников.
     * Узкие точные соответствия не затрагивают «Размер упаковки», «Цвет каркаса» и т.п.
     */
    public static function canonicalSlugForName(string $name): string
    {
        return match (mb_strtolower(trim($name))) {
            'цвет', 'color' => self::SLUG_COLOR,
            'размер', 'size' => self::SLUG_SIZE,
            default => Str::slug($name),
        };
    }
'''
text = replace_once(text, anchor, method, 'Attribute canonical slug method')
path.write_text(text)


path = Path('services/backend/app/Services/Catalog/Integrations/Svetofor1CCatalogImport.php')
text = path.read_text()
old = '''            $attribute = Attribute::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'type' => 'text',
                    'is_filterable' => true,
                    'is_use_in_variations' => false,
                    'allow_custom_value' => true,
                    'sort_order' => 0,
                ]
            );'''
new = '''            $attributeSlug = Attribute::canonicalSlugForName($name);
            $isCanonicalColor = $attributeSlug === Attribute::SLUG_COLOR;
            $isCanonicalSize = $attributeSlug === Attribute::SLUG_SIZE;

            $attribute = Attribute::firstOrCreate(
                ['slug' => $attributeSlug],
                [
                    'name' => $name,
                    'type' => $isCanonicalColor ? 'color' : ($isCanonicalSize ? 'select' : 'text'),
                    'is_filterable' => true,
                    'is_use_in_variations' => $isCanonicalColor || $isCanonicalSize,
                    'allow_custom_value' => $isCanonicalSize || (! $isCanonicalColor),
                    'sort_order' => 0,
                ]
            );

            if ($isCanonicalColor || $isCanonicalSize) {
                $attribute->forceFill([
                    'type' => $isCanonicalColor ? 'color' : 'select',
                    'is_filterable' => true,
                    'is_use_in_variations' => true,
                    'allow_custom_value' => $isCanonicalSize,
                ])->saveQuietly();
            }'''
text = replace_once(text, old, new, '1C canonical characteristic slugs')
path.write_text(text)

print('Canonical product attribute cleanup patch applied successfully.')
