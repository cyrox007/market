<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use App\Services\Catalog\BulkCollectionMembership;
use App\Services\Product\RelatedProductsResolver;
use App\Services\Product\ProductManufacturer;
use App\Services\Product\BulkRelatedProducts;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class RelatedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'relatedProducts';

    protected static ?string $title = 'Сопутствующие товары';

    protected static ?string $recordTitleAttribute = 'name';

    /** @var array<int, array{position:int, source:string, visible:bool, manual:bool}>|null */
    private ?array $editorRows = null;

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // Related products belong to the group, not an individual color offer.
        return ! $ownerRecord->isVariant();
    }

    public function table(Table $table): Table
    {
        return $table
            // A relationship-only table displayed "No products" even while the
            // public API returned auto/series recommendations. Query all effective
            // rows (plus saved manual links hidden by the chosen display mode).
            ->query(fn (): Builder => $this->editorQuery())
            ->description(fn (): HtmlString => $this->recommendationsOverview())
            ->emptyStateHeading('Сопутствующие товары не назначены')
            ->emptyStateDescription('Выберите производителя и товары кнопкой «Массово добавить» или настройте рекомендации серии.')
            ->columns([
                TextColumn::make('display_order')
                    ->label('#')
                    ->state(fn (Product $record): int => $this->editorRows()[$record->id]['position'] ?? 0),
                TextColumn::make('recommendation_source')
                    ->label('Источник')
                    ->badge()
                    ->state(fn (Product $record): string => $this->editorRows()[$record->id]['source'] ?? 'Автоподбор'),
                TextColumn::make('recommendation_visibility')
                    ->label('Показ')
                    ->badge()
                    ->state(fn (Product $record): string => ($this->editorRows()[$record->id]['visible'] ?? false)
                        ? 'На сайте' : 'Не показывается'),
                ImageColumn::make('recommendation_image')
                    ->state(function (Product $record): ?string {
                        if ($record->main_image_url) {
                            return $record->main_image_url;
                        }
                        foreach ($record->variants as $variant) {
                            if ($variant->main_image_url) {
                                return $variant->main_image_url;
                            }
                        }
                        return null;
                    })
                    ->label('Изображение')
                    ->circular()
                    ->size(50),

                TextColumn::make('name')
                    ->label('Название товара')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SKU скопирован'),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->headerActions([
                Action::make('bulkAddRelated')
                    ->label('Массово добавить')
                    ->icon('heroicon-o-squares-plus')
                    ->modalHeading('Массовое добавление сопутствующих товаров')
                    ->modalDescription('Сначала отфильтруйте фабрику, затем выберите конкретные товары. Фильтр сам ничего не добавляет. Выбираются карточки товаров, а не отдельные цветовые SKU.')
                    ->form([
                        Select::make('operation')
                            ->label('Куда добавить')
                            ->options([
                                'manual' => 'В сопутствующие текущего товара (двусторонняя связь)',
                                'series' => 'В мебельную серию (взаимные рекомендации)',
                            ])
                            ->default('manual')
                            ->live()
                            ->required(),
                        Select::make('collection_id')
                            ->label('Мебельная серия')
                            ->options(fn (): array => ProductCollection::query()->active()
                                ->where('is_auto', false)->whereNull('scope_type')
                                ->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->visible(fn ($get): bool => $get('operation') === 'series')
                            ->required(fn ($get): bool => $get('operation') === 'series')
                            ->helperText('Текущий товар и выбранные карточки будут включены в эту серию.'),
                        Select::make('manufacturer_name')
                            ->label('Производитель / фабрика')
                            ->options(fn (): array => collect(app(ProductManufacturer::class)->options())
                                ->pluck('name', 'name')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('target_product_ids', []))
                            ->helperText('Необязательно. Ограничивает список для выбора, но не назначает всю фабрику.'),
                        TextInput::make('product_search')
                            ->label('Название серии или товара')
                            ->placeholder('Например, Бруно')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn ($set) => $set('target_product_ids', [])),
                        Select::make('target_product_ids')
                            ->label('Выберите товары')
                            ->multiple()
                            ->live()
                            ->searchable()
                            ->options(fn ($get): array => $this->relatedProductOptions(
                                $get('manufacturer_name'), $get('product_search')))
                            ->getSearchResultsUsing(fn (string $search, $get): array => $this->relatedProductOptions(
                                $get('manufacturer_name'), $get('product_search'), $search))
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                                ->whereIn('id', $values)->get(['id', 'name', 'sku'])
                                ->mapWithKeys(fn (Product $product): array => [
                                    $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
                                ])->all())
                            ->required()
                            ->rules(['array', 'max:99'])
                            ->helperText('Отметьте нужные карточки. До 99 товаров за одну операцию, без самого текущего товара.'),
                        Placeholder::make('bulk_selection_preview')
                            ->label('К добавлению')
                            ->content(fn ($get): string => count((array) ($get('target_product_ids') ?? []))
                                . ' товаров. Подтвердите действие, чтобы сохранить связи.'),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        Gate::authorize('update', $owner);
                        $targetIds = array_values(array_unique(array_map('intval',
                            (array) ($data['target_product_ids'] ?? []))));
                        if ($targetIds === [] || count($targetIds) > 99) {
                            Notification::make()->title('Выберите от 1 до 99 товаров')
                                ->danger()->send();
                            return;
                        }

                        // Revalidate the submitted IDs against the manufacturer and
                        // name filters, not only the options presented by Livewire.
                        $valid = $this->relatedProductsQuery(
                            $data['manufacturer_name'] ?? null, $data['product_search'] ?? null
                        )->whereIn('products.id', $targetIds)->pluck('products.id')
                            ->map(fn ($id): int => (int) $id)->all();
                        if (count($valid) !== count($targetIds)) {
                            Notification::make()->title('Выбор товаров изменился')
                                ->body('Обновите список и выберите только активные товары указанной фабрики.')
                                ->danger()->send();
                            return;
                        }
                        try {
                            if (($data['operation'] ?? 'manual') === 'series') {
                                $collection = ProductCollection::findOrFail((int) ($data['collection_id'] ?? 0));
                                Gate::authorize('update', $collection);
                                foreach (Product::query()->whereIn('id', $valid)->get() as $target) {
                                    Gate::authorize('update', $target);
                                }
                                $result = app(BulkCollectionMembership::class)->apply(
                                    (int) $collection->id, array_merge([(int) $owner->id], $valid),
                                    'add', true
                                );
                                $message = "Добавлено в серию: {$result['added']} товаров.";
                            } else {
                                foreach (Product::query()->whereIn('id', $valid)->get() as $target) {
                                    Gate::authorize('update', $target);
                                }
                                $result = app(BulkRelatedProducts::class)->apply(
                                    [(int) $owner->id], $valid, 'add'
                                );
                                $message = "Добавлено двусторонних связей: {$result['added']}.";
                            }
                            $this->editorRows = null;
                            Notification::make()->title('Сопутствующие товары обновлены')
                                ->body($message)->success()->send();
                        } catch (ValidationException $error) {
                            Notification::make()->title('Не удалось добавить товары')
                                ->body(implode(' ', $error->validator->errors()->all()))
                                ->danger()->send();
                        }
                    }),
                Action::make('manageSeriesRecommendations')
                    ->label('Управлять сериями')
                    ->icon('heroicon-o-rectangle-stack')
                    ->modalHeading('Сопутствующие товары мебельной серии')
                    ->form([
                        Select::make('collection_id')
                            ->label('Мебельная серия')
                            ->options(fn (): array => ProductCollection::query()
                                ->active()->where('is_auto', false)->whereNull('scope_type')
                                ->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->helperText('Существующая ручная подборка товаров. Рекомендации настраиваются здесь же, без отдельного механизма.'),
                        Select::make('operation')
                            ->label('Действие')
                            ->options([
                                'add' => 'Добавить эту карточку в серию',
                                'remove' => 'Убрать эту карточку из серии',
                                'enable' => 'Включить взаимные рекомендации всей серии',
                                'disable' => 'Выключить взаимные рекомендации всей серии',
                            ])
                            ->default('add')->required()
                            ->helperText('При добавлении товара взаимные рекомендации серии включаются. Ручные связи сохраняются.'),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $collection = ProductCollection::findOrFail((int) ($data['collection_id'] ?? 0));
                        Gate::authorize('update', $owner);
                        Gate::authorize('update', $collection);
                        try {
                            $operation = (string) ($data['operation'] ?? 'add');
                            if (in_array($operation, ['enable', 'disable'], true)) {
                                if (! $collection->products()->where('products.id', $owner->id)->exists()) {
                                    throw ValidationException::withMessages([
                                        'collection_id' => 'Товар не входит в выбранную серию. Сначала добавьте его в серию.',
                                    ]);
                                }
                                $collection->forceFill([
                                    'recommend_collection_members' => $operation === 'enable',
                                ])->save();
                                Notification::make()
                                    ->title('Рекомендации серии обновлены')->success()->send();
                                return;
                            }

                            $result = app(BulkCollectionMembership::class)->apply(
                                (int) $collection->id,
                                [(int) $owner->id],
                                $operation,
                                $operation === 'add'
                            );
                            Notification::make()
                                ->title('Сопутствующие товары серии обновлены')
                                ->body("Добавлено в серию: {$result['added']}; убрано: {$result['removed']}.")
                                ->success()->send();
                        } catch (ValidationException $error) {
                            Notification::make()
                                ->title('Не удалось обновить серию')
                                ->body(implode(' ', $error->validator->errors()->all()))
                                ->danger()->send();
                        }
                    }),
                Action::make('recommendationMode')
                    ->label('Режим рекомендаций')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->fillForm(fn (): array => ['related_products_mode' => $this->getOwnerRecord()->related_products_mode ?? 'legacy'])
                    ->form([
                        Select::make('related_products_mode')
                            ->label('Режим отображения')
                            ->options([
                                'legacy' => 'Ручные связи + серии (ручные первыми, остальное автоматически)',
                                'manual' => 'Только вручную выбранные товары',
                                'automatic' => 'Автоматическая подборка',
                                'off' => 'Выключено',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $mode = $data['related_products_mode'] === 'legacy' ? null : $data['related_products_mode'];
                        // Vanilo Product mass-assignment does not include this
                        // extension attribute; update() silently discards it.
                        $owner->forceFill(['related_products_mode' => $mode])->save();
                        Product::flushAllProductCaches();
                        Notification::make()->title('Режим рекомендаций сохранён')->success()->send();
                    }),
                Action::make('attach')
                    ->label('Добавить сопутствующий товар')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('related_product_id')
                            ->label('Товар')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $owner = $this->getOwnerRecord();
                                $existingIds = $owner->relatedProducts()->pluck('products.id')->toArray();

                                return Product::query()
                                    ->whereNull('parent_product_id') // не показываем вариации
                                    ->where('id', '!=', $owner->getKey())
                                    ->whereNotIn('id', $existingIds)
                                    ->active()
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn(Product $product) => [
                                        $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $product = Product::find($value);
                                return $product ? $product->name . ' (SKU: ' . $product->sku . ')' : null;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $related = Product::find($data['related_product_id'] ?? null);

                        if (!$related) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Выбранный товар не найден')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($owner->is($related)) {
                            Notification::make()
                                ->title('Ошибка')
                                ->body('Нельзя добавить товар в сопутствующие к самому себе')
                                ->danger()
                                ->send();

                            return;
                        }

                        $owner->attachRelatedProduct($related);

                        Notification::make()
                            ->title('Сопутствующий товар добавлен')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make('order')
                    ->label('Порядок ручной связи')
                    ->visible(fn (Product $record): bool => $this->editorRows()[$record->id]['manual'] ?? false)
                    ->form([
                        TextInput::make('sort_order')->label('Порядок')->integer()->minValue(0)->required(),
                    ])
                    ->fillForm(fn (Product $record): array => [
                        'sort_order' => (int) ($this->getOwnerRecord()->relatedProducts()
                            ->where('products.id', $record->id)
                            ->first()?->pivot?->sort_order ?? 0),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $this->getOwnerRecord()->relatedProducts()->updateExistingPivot($record->id, [
                            'sort_order' => (int) $data['sort_order'],
                        ]);
                        Product::flushAllProductCaches();
                        $this->editorRows = null;
                    }),
                Action::make('detach')
                    ->label('Убрать ручную связь')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (Product $record): bool => $this->editorRows()[$record->id]['manual'] ?? false)
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $this->getOwnerRecord()->detachRelatedProduct($record);
                        $this->editorRows = null;
                        Notification::make()->title('Ручная связь удалена')
                            ->success()->send();
                    }),
            ]);
    }

    /** IDs shown on the site, followed by saved manual links hidden by the chosen mode. */
    private function editorRows(): array
    {
        if ($this->editorRows !== null) {
            return $this->editorRows;
        }
        $owner = $this->getOwnerRecord();
        $effective = app(RelatedProductsResolver::class)->forProduct($owner);
        $manual = $owner->relatedProducts()
            ->whereNull('parent_product_id')
            ->where('products.id', '!=', $owner->id)
            ->get();

        $manualIds = $manual->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rows = [];
        foreach ($effective as $entry) {
            $item = $entry['product'];
            $label = match ($entry['source']) {
                'manual' => 'Вручную',
                'series' => 'Серия: ' . ($entry['series_name'] ?? ''),
                'category' => 'По категории',
                default => 'Автоподбор',
            };
            $rows[(int) $item->id] = [
                'position' => count($rows) + 1,
                'source' => $label,
                'visible' => true,
                'manual' => in_array((int) $item->id, $manualIds, true),
            ];
        }
        foreach ($manual as $item) {
            $id = (int) $item->id;
            if (isset($rows[$id])) {
                // A manual link can also match a series in automatic mode.
                if ($rows[$id]['source'] !== 'Вручную') {
                    $rows[$id]['source'] .= ' + ручная связь';
                }
                continue;
            }
            $rows[$id] = [
                'position' => count($rows) + 1,
                'source' => 'Вручную',
                'visible' => false,
                'manual' => true,
            ];
        }
        return $this->editorRows = $rows;
    }

    private function editorQuery(): Builder
    {
        $rows = $this->editorRows();
        $ids = array_keys($rows);
        $query = Product::query()->with(['media', 'variants.media'])->whereNull('parent_product_id')
            ->whereIn('products.id', $ids);
        if ($ids !== []) {
            $cases = collect($ids)->map(fn (int $id, int $idx): string =>
                'WHEN ' . $id . ' THEN ' . $idx)->implode(' ');
            $query->orderByRaw('CASE products.id ' . $cases . ' ELSE 99999 END');
        }
        return $query;
    }

    private function relatedProductsQuery(?string $manufacturer, ?string $name): Builder
    {
        $query = Product::query()->active()
            ->whereNull('parent_product_id')
            ->where('products.id', '!=', $this->getOwnerRecord()->id);
        if (filled($manufacturer)) {
            app(ProductManufacturer::class)->filter($query, $manufacturer);
        }
        if (filled($name)) {
            $query->where(fn (Builder $q): Builder => $q
                ->where('name', 'like', '%' . trim($name) . '%')
                ->orWhere('sku', 'like', '%' . trim($name) . '%'));
        }
        return $query;
    }

    private function relatedProductOptions(?string $manufacturer, ?string $name, string $search = ''): array
    {
        $query = $this->relatedProductsQuery($manufacturer, $name);
        if (trim($search) !== '') {
            $query->where(fn (Builder $q): Builder => $q
                ->where('name', 'like', '%' . trim($search) . '%')
                ->orWhere('sku', 'like', '%' . trim($search) . '%'));
        }
        return $query->orderBy('name')->limit(60)->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->name . ' (SKU: ' . $product->sku . ')',
            ])->all();
    }

    /**
     * The existing relation editor shows BOTH managed manual links and the
     * derived recommendations that the public API actually returns.
     * Sources are explicit: nothing is silently materialized as a manual link.
     */
    private function recommendationsOverview(): HtmlString
    {
        $owner = $this->getOwnerRecord();
        $mode = match ($owner->related_products_mode) {
            'off' => 'Отключено',
            'manual' => 'Только вручную',
            'automatic' => 'Рекомендации серий и автоматический подбор',
            default => 'Ручные рекомендации + серии',
        };

        $series = ProductCollection::query()
            ->where('is_auto', false)->whereNull('scope_type')
            ->whereHas('products', fn ($query) => $query->where('products.id', $owner->id))
            ->orderBy('name')->get(['id', 'name', 'is_active', 'recommend_collection_members']);
        $seriesLabels = $series->map(function (ProductCollection $collection): string {
            $state = $collection->is_active && $collection->recommend_collection_members
                ? 'рекомендации включены' : 'рекомендации выключены';
            return '<span class="inline-block me-2">' . e($collection->name) . ' (' . e($state) . ')</span>';
        })->implode(' ');

        $rows = $this->editorRows();
        $visible = count(array_filter($rows, fn (array $row): bool => $row['visible']));
        $manual = count(array_filter($rows, fn (array $row): bool => $row['manual']));
        return new HtmlString(
            '<div class="space-y-1 text-sm">'
            . '<p><strong>Режим:</strong> ' . e($mode)
            . '. <strong>Что сейчас показывается покупателю:</strong> ' . $visible
            . '. <strong>Ручных связей:</strong> ' . $manual . '.</p>'
            . '<p><strong>Участие в сериях:</strong> '
            . ($seriesLabels !== '' ? $seriesLabels : 'Нет назначенных серий') . '</p>'
            . '<p class="text-gray-500">Ниже единая таблица: ручные связи, рекомендации серий и автоподбор. '
            . 'Строки с пометкой «Не показывается» сохранены, но скрыты текущим режимом. '
            . 'Чтобы массово добавить товары по производителю, нажмите «Массово добавить».</p>'
            . '</div>'
        );
    }
}
