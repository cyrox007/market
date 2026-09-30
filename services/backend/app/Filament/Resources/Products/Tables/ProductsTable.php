<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Inventory\Stock\ResolveWarehouseStockRowAction;
use App\Actions\Product\BuildMergedProductDraftAction;
use App\Actions\Product\Data\MergedProductDraft;
use App\Actions\Product\Data\MergeProductsIntoVariableProductData;
use App\Actions\Product\MergeProductsIntoVariableProductAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Attribute;
use App\Models\Product\Product;
use App\Models\Settings\ProductStockSettings;
use App\Services\Inventory\WarehouseStockResolver;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Vanilo\Product\Models\ProductState;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn($record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(function ($query) {
                $query
                    ->whereNull('parent_product_id')
                    ->with([
                        'taxons',
                        'media',
                        'manufacturer',
                        'warehouseStocks',
                        'variants' => fn($q) => $q->where('state', ProductState::ACTIVE),
                        'variants.warehouseStocks',
                        'variants.media',
                    ]);
            })
            ->columns([
                ImageColumn::make('admin_image')
                    ->label('Фото')
                    ->state(function (Product $record): ?string {
                        // Сразу используем оригинал: thumb-конверсии MediaLibrary могут ещё
                        // стоять в очереди после массового Ozon-импорта.
                        if ($record->main_image_url) {
                            return $record->main_image_url;
                        }

                        // У вариативного товара реальные фотографии прежде всего принадлежат ТП.
                        // Пока представительское фото родителя ещё не обработано, показываем
                        // главное фото первого предложения, чтобы список не выглядел пустым.
                        foreach ($record->variants as $variant) {
                            if ($variant->main_image_url) {
                                return $variant->main_image_url;
                            }
                        }

                        return null;
                    })
                    ->square()
                    ->size(52),

                TextColumn::make('name')
                    ->label(__('filament/admin_sv/product_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->tooltip(fn($record) => $record?->name),

                TextColumn::make('sku')
                    ->label(__('filament/admin_sv/product_resource.sku'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('taxons_list')
                    ->label(__('filament/admin_sv/product_resource.taxons'))
                    ->state(fn(Product $record): array => $record->relationLoaded('taxons')
                        ? $record->taxons->pluck('name')->filter()->values()->all()
                        : [])
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('manufacturer.name')
                    ->label('Производитель')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('price')
                    ->label(__('filament/admin_sv/product_resource.price'))
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('state')
                    ->label(__('filament/admin_sv/product_resource.state'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        ProductState::ACTIVE => 'success',
                        ProductState::DRAFT => 'gray',
                        ProductState::INACTIVE => 'warning',
                        ProductState::UNLISTED => 'info',
                        ProductState::UNAVAILABLE => 'danger',
                        ProductState::RETIRED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        ProductState::ACTIVE => 'Активен',
                        ProductState::DRAFT => 'Черновик',
                        ProductState::INACTIVE => 'Неактивен',
                        ProductState::UNLISTED => 'Скрыт',
                        ProductState::UNAVAILABLE => 'Недоступен',
                        ProductState::RETIRED => 'Снят с продажи',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('stock_display')
                    ->label(__('filament/admin_sv/product_resource.stock'))
                    ->state(fn(Product $record): int => self::resolveListStock($record))
                    ->numeric()
                    ->alignEnd()
                    ->tooltip(fn(Product $record): ?string => ProductStockSettings::getInstance()->warehouse_accounting_enabled
                        ? ($record->isVariable()
                            ? 'Сумма остатков активных вариаций по складам'
                            : 'Остаток на привязанном складе (или первом из справочника)')
                        : ($record->isVariable()
                            ? 'Сумма остатков активных вариаций'
                            : null)),
                TextColumn::make('warehouse_stocks_display')
                    ->label('Остатки по складам')
                    ->html()
                    ->formatStateUsing(function (Product $record): string {
                        if (!$record->relationLoaded('warehouseStocks')) {
                            $record->load('warehouseStocks.warehouse');
                        }

                        if ($record->isVariable() && $record->warehouseStocks->isEmpty()) {
                            return '<span class="text-gray-400 text-xs">см. вариации</span>';
                        }

                        if ($record->warehouseStocks->isEmpty()) {
                            return '—';
                        }

                        return $record->warehouseStocks->map(function ($stock) {
                            $name = $stock->warehouse->name ?? 'Склад #' . $stock->warehouse_id;
                            return e($name) . ': ' . (int) $stock->quantity;
                        })->implode('<br>');
                    })
                    ->tooltip('Остатки по каждому складу (для простых товаров)')
                    ->toggleable()
                    ->sortable(false),

                TextColumn::make('variants_count')
                    ->label('Вариации')
                    ->counts('variants')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/product_resource.priority'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('slug')
                    ->label(__('filament/admin_sv/product_resource.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('original_price')
                    ->label(__('filament/admin_sv/product_resource.original_price'))
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('length')
                    ->label(__('filament/admin_sv/product_resource.length'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('width')
                    ->label(__('filament/admin_sv/product_resource.width'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('height')
                    ->label(__('filament/admin_sv/product_resource.height'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('weight')
                    ->label(__('filament/admin_sv/product_resource.weight'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ext_title')
                    ->label(__('filament/admin_sv/product_resource.ext_title'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('units_sold')
                    ->label(__('filament/admin_sv/product_resource.units_sold'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_sale_at')
                    ->label(__('filament/admin_sv/product_resource.last_sale_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tax_category_id')
                    ->label(__('filament/admin_sv/product_resource.tax_category_id'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('backorder')
                    ->label(__('filament/admin_sv/product_resource.backorder'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gtin')
                    ->label(__('filament/admin_sv/product_resource.gtin'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping_category_id')
                    ->label(__('filament/admin_sv/product_resource.shipping_category_id'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subtitle')
                    ->label(__('filament/admin_sv/product_resource.subtitle'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('filament/admin_sv/product_resource.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/product_resource.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament/admin_sv/product_resource.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label(__('filament/admin_sv/product_resource.state'))
                    ->options([
                        ProductState::ACTIVE => 'Активен',
                        ProductState::DRAFT => 'Черновик',
                        ProductState::INACTIVE => 'Неактивен',
                        ProductState::UNLISTED => 'Скрыт',
                        ProductState::UNAVAILABLE => 'Недоступен',
                        ProductState::RETIRED => 'Снят с продажи',
                    ]),

                SelectFilter::make('taxons')
                    ->label(__('filament/admin_sv/product_resource.taxons'))
                    ->relationship(
                        'taxons',
                        'name',
                        modifyQueryUsing: fn($query) => $query->where('is_active', true)->orderBy('name')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('manufacturer_id')
                    ->label('Производитель')
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),

                ...self::attributeFilters(),
            ])
            ->filtersFormSchema(function (array $filters): array {
                $baseFilters = array_values(array_filter([
                    $filters['state'] ?? null,
                    $filters['taxons'] ?? null,
                    $filters['manufacturer_id'] ?? null,
                ]));

                $attributeFilters = array_values(array_filter(
                    $filters,
                    static fn ($filter, string $name): bool => str_starts_with($name, 'attribute_value_'),
                    ARRAY_FILTER_USE_BOTH,
                ));

                if ($attributeFilters === []) {
                    return $baseFilters;
                }

                return [
                    ...$baseFilters,
                    Section::make('Характеристики')
                        ->description('Дополнительные фильтры по характеристикам товара. Разверните только при необходимости.')
                        ->schema($attributeFilters)
                        ->columns([
                            'sm' => 2,
                            'lg' => 3,
                            'xl' => 4,
                        ])
                        ->collapsible()
                        ->collapsed()
                        ->columnSpanFull(),
                ];
            })
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->filtersFormWidth(Width::SevenExtraLarge)
            ->filtersFormMaxHeight('75vh')
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('merge_into_variable')
                        ->label('Объединить в вариативный товар')
                        ->icon('heroicon-o-squares-plus')
                        ->requiresConfirmation()
                        ->modalWidth(Width::FiveExtraLarge)
                        ->stickyModalHeader()
                        ->stickyModalFooter()
                        ->modalDescription('Будет создана общая карточка, а все выбранные товары станут её торговыми предложениями.')
                        ->deselectRecordsAfterCompletion()
                        ->form(fn(Collection $records): array => self::mergeIntoVariableForm($records))
                        ->action(function (Collection $records, array $data) {
                            if ($records->count() < 2) {
                                Notification::make()
                                    ->title('Выберите минимум 2 товара')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $variantLabelOverrides = [];

                                foreach ($data['variants'] ?? [] as $row) {
                                    $productId = (int) ($row['product_id'] ?? 0);
                                    $label = trim((string) ($row['variant_label'] ?? ''));
                                    if ($productId > 0 && $label !== '') {
                                        $variantLabelOverrides[$productId] = $label;
                                    }
                                }

                                $draft = app(BuildMergedProductDraftAction::class)->execute($records);

                                $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
                                    new MergeProductsIntoVariableProductData(
                                        productIds: $records->pluck('id')->map(fn($id) => (int) $id)->all(),
                                        variantLabelOverrides: $variantLabelOverrides,
                                        name: trim((string) ($data['parent_name'] ?? '')) ?: null,
                                    ),
                                );

                                $body = 'Создано торговых предложений: ' . $parent->variants()->count();
                                if ($draft->warnings !== []) {
                                    $body .= "\n\nВнимание: " . implode(' ', $draft->warnings);
                                }

                                $notification = Notification::make()
                                    ->title('Товары объединены в вариативный')
                                    ->body($body);
                                $draft->warnings !== []
                                    ? $notification->warning()->persistent()
                                    : $notification->success();
                                $notification->send();

                                redirect(ProductResource::getUrl('edit', ['record' => $parent]));
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Не удалось объединить товары')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Фильтры по значениям атрибутов (характеристикам) с is_filterable = true.
     * Для каждого такого атрибута (например, «Производитель») добавляется отдельный фильтр.
     *
     * @return array<int, SelectFilter>
     */
    /**
     * Остаток для списка товаров: вариативный — сумма по вариациям; простой — со склада или из поля stock.
     */
    protected static function resolveListStock(Product $record): int
    {
        $settings = ProductStockSettings::getInstance();

        if ($settings->warehouse_accounting_enabled) {
            if ($record->isVariable()) {
                return (int) round(app(WarehouseStockResolver::class)->resolveForProduct($record, null) ?? 0);
            }

            $row = app(ResolveWarehouseStockRowAction::class)->execute(
                $record,
                null,
                (bool) $settings->fallback_to_first_warehouse,
            );

            if ($row !== null) {
                return (int) round((float) $row->quantity);
            }

            return (int) ($record->getAttributes()['stock'] ?? 0);
        }

        return (int) ($record->stock ?? 0);
    }

    /**
     * @param  Collection<int, Product>  $records
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function mergeIntoVariableForm(Collection $records): array
    {
        $draft = app(BuildMergedProductDraftAction::class)->execute($records);

        $repeaterDefaults = $records->map(fn(Product $product) => [
            'product_id' => $product->id,
            'product_label' => $product->name . ' (SKU: ' . $product->sku . ')',
            'variant_label' => $draft->variantLabels[$product->id] ?? $product->name,
        ])->values()->all();

        return [
            TextInput::make('parent_name')
                ->label('Название общей карточки')
                ->helperText('Собрано из общей части названий без кода 1С. Карточка создаётся заново, ЧПУ формируется из названия.')
                ->default($draft->name)
                ->required()
                ->maxLength(255),

            Placeholder::make('merge_warnings')
                ->hiddenLabel()
                ->visible($draft->warnings !== [])
                ->content(new HtmlString(self::buildMergeWarningsHtml($draft))),

            Placeholder::make('merge_categories')
                ->label('Категории общей карточки')
                ->content(new HtmlString(self::buildMergeCategoriesHtml($draft, $records))),

            Placeholder::make('common_specs')
                ->label('Общие характеристики')
                ->content(self::buildMergeCommonSpecsText($draft)),

            Placeholder::make('differing_specs')
                ->label('Отличающиеся характеристики (остаются у вариантов)')
                ->content(new HtmlString(self::buildMergeDifferingSpecsHtml($draft, $records))),

            Repeater::make('variants')
                ->label('Торговые предложения')
                ->helperText('Каждый выбранный товар станет торговым предложением. Название вариации — атрибут «Вариант».')
                ->schema([
                    Hidden::make('product_id'),
                    TextInput::make('product_label')
                        ->label('Товар')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('variant_label')
                        ->label('Название вариации')
                        ->required()
                        ->maxLength(255),
                ])
                ->default($repeaterDefaults)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columns(2),
        ];
    }

    protected static function buildMergeWarningsHtml(MergedProductDraft $draft): string
    {
        $items = collect($draft->warnings)->map(fn(string $warning) => '<li>' . e($warning) . '</li>')->implode('');

        return '<div style="border:1px solid var(--warning-300);background:var(--warning-50);color:var(--warning-800);border-radius:.5rem;padding:.75rem;font-size:.875rem">'
            . '<p style="font-weight:600">Проверьте перед объединением</p>'
            . '<ul style="list-style:disc;padding-inline-start:1.25rem;margin-top:.25rem">' . $items . '</ul></div>';
    }

    /**
     * @param  Collection<int, Product>  $records
     */
    protected static function buildMergeCategoriesHtml(MergedProductDraft $draft, Collection $records): string
    {
        if ($draft->categoryBreakdown === []) {
            return '<p style="font-size:.875rem;color:var(--gray-500)">У выбранных товаров нет категорий.</p>';
        }

        $total = $records->count();
        $rows = collect($draft->categoryBreakdown)->map(function (array $category) use ($records, $total, $draft): string {
            $count = count($category['product_ids']);
            $details = '';
            if ($draft->categoriesDiffer && $count < $total) {
                $names = $records->whereIn('id', $category['product_ids'])
                    ->map(fn(Product $product) => e(Str::limit($product->name, 60)))
                    ->implode('; ');
                $details = ' <span style="color:var(--warning-700)">(' . $names . ')</span>';
            }

            return '<li>' . e($category['name']) . ' — ' . $count . ' из ' . $total . $details . '</li>';
        })->implode('');

        return '<ul style="list-style:disc;padding-inline-start:1.25rem;font-size:.875rem">' . $rows . '</ul>';
    }

    protected static function buildMergeCommonSpecsText(MergedProductDraft $draft): string
    {
        $count = count($draft->commonAttributes);

        return $count > 0
            ? "{$count} шт. — будут скопированы в общую карточку"
            : 'Нет совпадающих характеристик';
    }

    /**
     * @param  Collection<int, Product>  $records
     */
    protected static function buildMergeDifferingSpecsHtml(MergedProductDraft $draft, Collection $records): string
    {
        if ($draft->differingAttributes === []) {
            return '<p style="font-size:.875rem;color:var(--gray-500)">Характеристики совпадают — различаются только названия товаров.</p>';
        }

        $headers = $records->map(fn(Product $product): string => '<th style="padding:.25rem .5rem;text-align:left;font-weight:600">'
            . e($draft->variantLabels[$product->id] ?? Str::limit($product->name, 40))
            . '</th>')->implode('');

        $rows = collect($draft->differingAttributes)->map(function (array $attribute) use ($records): string {
            $cells = $records->map(fn(Product $product): string => '<td style="padding:.25rem .5rem">'
                . e($attribute['values'][$product->id] ?? '—')
                . '</td>')->implode('');

            return '<tr style="border-top:1px solid var(--gray-200)"><td style="padding:.25rem .5rem;color:var(--gray-500)">' . e($attribute['name']) . '</td>' . $cells . '</tr>';
        })->implode('');

        return '<div style="max-width:100%;max-height:16rem;overflow:auto;border:1px solid var(--gray-200);border-radius:.5rem;font-size:.875rem">'
            . '<table style="width:100%;min-width:max-content">'
            . '<thead><tr><th style="padding:.25rem .5rem;text-align:left;font-weight:600">Характеристика</th>' . $headers . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';
    }

    protected static function attributeFilters(): array
    {
        $attributes = Attribute::query()
            ->where('is_filterable', true)
            // SelectFilter работает только со справочными значениями AttributeValue.
            // Текстовые характеристики с одним custom_value раньше создавали пустые
            // выпадающие списки и раздували панель фильтров без пользы.
            ->whereHas('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $filters = [];
        foreach ($attributes as $attribute) {
            $attributeId = $attribute->id;
            $filters[] = SelectFilter::make('attribute_value_' . $attributeId)
                ->label($attribute->name)
                ->relationship(
                    'attributeValues',
                    'value',
                    modifyQueryUsing: function ($query) use ($attributeId) {
                        return $query
                            ->where('product_attribute_values.attribute_id', $attributeId)
                            ->orderBy('product_attribute_values.sort_order')
                            ->orderBy('product_attribute_values.value');
                    }
                )
                ->searchable();
        }

        return $filters;
    }
}
