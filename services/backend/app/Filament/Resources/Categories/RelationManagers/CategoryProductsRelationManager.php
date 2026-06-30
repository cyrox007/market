<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Vanilo\Product\Models\ProductState;

class CategoryProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = null;

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Получаем категорию
                $category = $this->getOwnerRecord();

                // Полностью переопределяем запрос через промежуточную таблицу model_taxons
                // так как morphToMany может не работать напрямую в Filament 4
                return Product::query()
                    ->whereIn('id', function ($subQuery) use ($category) {
                    $subQuery->select('model_id')
                        ->from('model_taxons')
                        ->where('taxon_id', $category->id)
                        ->where('model_type', Product::class);
                });
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/category_products_relation_manager.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn(Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
                TextColumn::make('sku')
                    ->label(__('filament/admin_sv/category_products_relation_manager.sku'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('filament/admin_sv/category_products_relation_manager.price'))
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('state')
                    ->label(__('filament/admin_sv/category_products_relation_manager.state'))
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
                    }),
                TextColumn::make('stock')
                    ->label(__('filament/admin_sv/category_products_relation_manager.stock'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/category_products_relation_manager.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/admin_sv/category_products_relation_manager.title');
    }

}
