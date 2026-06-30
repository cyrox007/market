<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Inventory\Stock\ResolveWarehouseStockRowAction;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
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
                    ]);
            })
            ->columns([
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
                        ->modalDescription('Товары станут торговыми предложениями одного родителя. ID 1С, SKU и остатки по складам сохраняются.')
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
                                $parentId = (int) $data['parent_id'];
                                $variantLabelOverrides = [];

                                foreach ($data['variants'] ?? [] as $row) {
                                    $productId = (int) ($row['product_id'] ?? 0);
                                    if ($productId <= 0 || $productId === $parentId) {
                                        continue;
                                    }

                                    $label = trim((string) ($row['variant_label'] ?? ''));
                                    if ($label !== '') {
                                        $variantLabelOverrides[$productId] = $label;
                                    }
                                }

                                $parentName = trim((string) ($data['parent_name'] ?? ''));

                                $parent = app(MergeProductsIntoVariableProductAction::class)->execute(
                                    new MergeProductsIntoVariableProductData(
                                        parentId: $parentId,
                                        productIds: $records->pluck('id')->map(fn($id) => (int) $id)->all(),
                                        variantLabelOverrides: $variantLabelOverrides,
                                        mergeCategories: (bool) ($data['merge_categories'] ?? false),
                                        parentName: $parentName !== '' ? $parentName : null,
                                    ),
                                );

                                Notification::make()
                                    ->title('Товары объединены в вариативный')
                                    ->body('Создано торговых предложений: ' . $parent->variants()->count())
                                    ->success()
                                    ->send();

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
        $productOptions = $records->mapWithKeys(fn(Product $product) => [
            $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
        ])->all();

        $namesById = $records->mapWithKeys(fn(Product $product) => [
            $product->id => $product->name,
        ])->all();

        $records->each(fn(Product $product) => $product->loadMissing(['attributes']));

        $repeaterDefaults = $records->map(fn(Product $product) => [
            'product_id' => $product->id,
            'product_label' => $product->name . ' (SKU: ' . $product->sku . ')',
            'variant_label' => $product->name,
        ])->values()->all();

        return [
            Select::make('parent_id')
                ->label('Родительский товар (карточка на сайте)')
                ->options($productOptions)
                ->default($records->first()?->id)
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, $set) use ($namesById): void {
                    if ($state && isset($namesById[$state])) {
                        $set('parent_name', $namesById[$state]);
                    }
                }),

            TextInput::make('parent_name')
                ->label('Название вариативного товара')
                ->default($records->first()?->name)
                ->required()
                ->maxLength(255),

            Toggle::make('merge_categories')
                ->label('Объединить категории на родителе')
                ->default(true),

            Placeholder::make('differing_specs')
                ->label('Отличающиеся характеристики')
                ->content(new HtmlString(self::buildMergeDifferingSpecsHtml($records))),

            Repeater::make('variants')
                ->label('Торговые предложения')
                ->helperText('Товары, которые станут ТП (кроме родителя). Название вариации — атрибут «Вариант».')
                ->schema([
                    Hidden::make('product_id'),
                    TextInput::make('product_label')
                        ->label('Товар')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn($get): bool => (int) ($get('product_id') ?? 0) !== (int) ($get('../../parent_id') ?? 0)),
                    TextInput::make('variant_label')
                        ->label('Название вариации')
                        ->required()
                        ->maxLength(255)
                        ->visible(fn($get): bool => (int) ($get('product_id') ?? 0) !== (int) ($get('../../parent_id') ?? 0)),
                ])
                ->default($repeaterDefaults)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columns(1),
        ];
    }

    /**
     * @param  Collection<int, Product>  $records
     */
    protected static function buildMergeDifferingSpecsHtml(Collection $records): string
    {
        $specsByProduct = $records->mapWithKeys(function (Product $product): array {
            $bySlug = [];
            foreach ($product->buildSpecificationsForApi() as $spec) {
                $bySlug[$spec['slug']] = $spec;
            }

            return [$product->id => $bySlug];
        });

        $allSlugs = $specsByProduct
            ->flatMap(fn(array $specs): array => array_keys($specs))
            ->unique()
            ->values();

        $differingSlugs = $allSlugs->filter(function (string $slug) use ($specsByProduct, $records): bool {
            $values = $records->map(function (Product $product) use ($specsByProduct, $slug): string {
                return (string) ($specsByProduct[$product->id][$slug]['value'] ?? '—');
            })->unique();

            return $values->count() > 1;
        })->values();

        if ($differingSlugs->isEmpty()) {
            return '<p class="text-sm text-gray-500">Характеристики совпадают — различаются только названия товаров.</p>';
        }

        $headers = $records->map(fn(Product $product): string => '<th class="px-2 py-1 text-left font-medium">'
            . e(Str::limit($product->name, 40))
            . '</th>')->implode('');

        $rows = $differingSlugs->map(function (string $slug) use ($records, $specsByProduct): string {
            $name = $slug;
            foreach ($specsByProduct as $specs) {
                if (isset($specs[$slug]['name'])) {
                    $name = $specs[$slug]['name'];
                    break;
                }
            }

            $cells = $records->map(function (Product $product) use ($specsByProduct, $slug): string {
                $value = $specsByProduct[$product->id][$slug]['value'] ?? '—';

                return '<td class="px-2 py-1">' . e((string) $value) . '</td>';
            })->implode('');

            return '<tr><td class="px-2 py-1 font-medium text-gray-600">' . e($name) . '</td>' . $cells . '</tr>';
        })->implode('');

        return '<div class="max-w-full max-h-64 overflow-auto rounded-lg border border-gray-200 text-sm">'
            . '<table class="w-full min-w-max">'
            . '<thead class="bg-gray-50 sticky top-0 z-10"><tr><th class="px-2 py-1 text-left">Характеристика</th>' . $headers . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';
    }

    protected static function attributeFilters(): array
    {
        $attributes = Attribute::query()
            ->where('is_filterable', true)
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
