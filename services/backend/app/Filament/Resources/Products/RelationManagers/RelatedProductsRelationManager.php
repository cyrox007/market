<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use App\Models\Product\ProductCollection;
use App\Services\Catalog\BulkCollectionMembership;
use App\Services\Product\RelatedProductsResolver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class RelatedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'relatedProducts';

    protected static ?string $title = 'Сопутствующие товары';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // Related products belong to the group, not an individual color offer.
        return ! $ownerRecord->isVariant();
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): HtmlString => $this->recommendationsOverview())
            ->columns([
                TextColumn::make('pivot.sort_order')
                    ->label('#')
                    ->numeric(),
                TextColumn::make('manual_source')
                    ->label('Источник')
                    ->state(fn (): string => 'Вручную'),
                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('images')
                    ->conversion('thumb')
                    ->label('Изображение')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(url('/images/placeholder.png')),

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
            ->actions([
                EditAction::make('order')
                    ->label('Порядок')
                    ->form([
                        TextInput::make('sort_order')->label('Порядок')->integer()->minValue(0)->required(),
                    ])
                    ->fillForm(fn (Product $record): array => ['sort_order' => (int) $record->pivot->sort_order])
                    ->action(function (Product $record, array $data): void {
                        $this->getOwnerRecord()->relatedProducts()->updateExistingPivot($record->id, [
                            'sort_order' => (int) $data['sort_order'],
                        ]);
                        Product::flushAllProductCaches();
                    }),
                Action::make('detach')
                    ->label('Убрать из сопутствующих')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $owner = $this->getOwnerRecord();
                        $owner->detachRelatedProduct($record);

                        Notification::make()
                            ->title('Сопутствующий товар удалён')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Убрать из сопутствующих')
                        ->action(function ($records): void {
                            $owner = $this->getOwnerRecord();
                            foreach ($records as $record) {
                                if ($record instanceof Product) {
                                    $owner->detachRelatedProduct($record);
                                }
                            }

                            Notification::make()
                                ->title('Сопутствующие товары удалены')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $owner = $this->getOwnerRecord();

                return $query
                    ->whereNull('parent_product_id')
                    ->where('products.id', '!=', $owner->getKey())
                    ->with('media');
            });
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

        $items = app(RelatedProductsResolver::class)->forProduct($owner);
        $rows = $items->map(function (array $entry): string {
            $product = $entry['product'];
            $source = match ($entry['source']) {
                'manual' => 'Вручную',
                'series' => 'Серия: ' . ($entry['series_name'] ?? ''),
                'category' => 'Та же категория',
                default => 'Автоподбор',
            };
            $url = ProductResource::getUrl('edit', ['record' => $product]);
            return '<li class="py-1"><a href="' . e($url) . '" class="underline">'
                . e($product->name) . '</a> <span class="text-gray-500">('
                . e($source) . ')</span></li>';
        })->implode('');

        return new HtmlString(
            '<div class="space-y-2 text-sm">'
            . '<p><strong>Режим:</strong> ' . e($mode) . '. '
            . 'Ручные связи редактируются в таблице ниже; серии — кнопкой «Управлять сериями».</p>'
            . '<p><strong>Участие в сериях:</strong> '
            . ($seriesLabels !== '' ? $seriesLabels : 'Нет назначенных серий') . '</p>'
            . '<div><strong>Что сейчас показывается покупателю (' . $items->count() . '):</strong>'
            . ($rows !== '' ? '<ul class="list-disc ps-5 mt-1">' . $rows . '</ul>' : ' Нет рекомендаций')
            . '</div></div>'
        );
    }
}
