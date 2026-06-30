<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;

class ProductCollectionProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Товары в подборке';

    protected static ?string $recordTitleAttribute = 'name';

    public function isReadOnly(): bool
    {
        return (bool) $this->getOwnerRecord()->is_auto;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pivot.sort_order')
                    ->label('#')
                    ->numeric()
                    ->width('60px')
                    ->alignCenter(),

                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('images')
                    ->conversion('thumb')
                    ->label('Изображение')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(url('/images/placeholder.png'))
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Название товара')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

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

                TextColumn::make('state')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'inactive' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Активен',
                        'draft' => 'Черновик',
                        'inactive' => 'Неактивен',
                        default => $state,
                    }),
            ])
            ->filters([
                //
            ])
            ->emptyStateHeading(fn () => $this->getOwnerRecord()->is_auto
                ? 'Автоматическая подборка'
                : 'Нет товаров')
            ->emptyStateDescription(fn () => $this->getOwnerRecord()->is_auto
                ? 'Товары подтягиваются по типу скоупа при сохранении или по кнопке «Синхронизировать товары». Ручное добавление недоступно.'
                : 'Добавьте товары вручную.')
            ->headerActions([
                Action::make('attach')
                    ->label('Добавить товар')
                    ->visible(fn () => ! $this->getOwnerRecord()->is_auto)
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('product_id')
                            ->label('Товар')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $collection = $this->getOwnerRecord();
                                $existingIds = $collection->products()->pluck('products.id')->toArray();
                                
                                return Product::query()
                                    ->whereNull('parent_product_id')
                                    ->active()
                                    ->when(!empty($existingIds), fn($q) => $q->whereNotIn('id', $existingIds))
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($product) => [
                                        $product->id => $product->name . ' (SKU: ' . $product->sku . ')'
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $product = Product::find($value);
                                return $product ? $product->name . ' (SKU: ' . $product->sku . ')' : null;
                            })
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(function () {
                                $collection = $this->getOwnerRecord();
                                $max = $collection->products()->max('product_product_collection.sort_order') ?? 0;
                                return $max + 1;
                            }),
                    ])
                    ->action(function (array $data): void {
                        $collection = $this->getOwnerRecord();
                        $collection->products()->attach($data['product_id'], [
                            'sort_order' => $data['sort_order'] ?? 0
                        ]);
                        $collection->flushHomeCaches();
                    })
                    ->successNotificationTitle('Товар добавлен'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Изменить порядок')
                    ->form([
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->required()
                            ->default(fn ($record) => $record->pivot->sort_order ?? 0),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['sort_order'] = $record->pivot->sort_order ?? 0;
                        return $data;
                    })
                    ->using(function (array $data, $record): void {
                        $owner = $this->getOwnerRecord();
                        $owner->products()->updateExistingPivot($record->id, [
                            'sort_order' => $data['sort_order']
                        ]);
                        $owner->flushHomeCaches();
                    }),
                DetachAction::make()
                    ->label('Удалить')
                    ->after(function (): void {
                        $this->getOwnerRecord()->flushHomeCaches();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->after(function (): void {
                            $this->getOwnerRecord()->flushHomeCaches();
                        }),
                ]),
            ])
            ->defaultSort('product_product_collection.sort_order', 'asc')
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->whereNull('parent_product_id')
                    ->with('media')
                    ->orderBy('product_product_collection.sort_order', 'asc');
            });
    }
}
