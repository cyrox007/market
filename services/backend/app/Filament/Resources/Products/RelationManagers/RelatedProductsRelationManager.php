<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RelatedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'relatedProducts';

    protected static ?string $title = 'Сопутствующие товары';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
}

