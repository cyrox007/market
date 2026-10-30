<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\ProductCollectionResource;
use App\Models\Product\ProductCollection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProductCollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (ProductCollection $record): string => ProductCollectionResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scope_type')
                    ->label('Тип скоупа')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'featured' => 'Популярное',
                        'new' => 'Новинки',
                        'sale' => 'Акции',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'featured',
                        'info' => 'new',
                        'danger' => 'sale',
                    ])
                    ->sortable(),
                ToggleColumn::make('is_auto')
                    ->label('Автоматическая')
                    ->sortable()
                    ->disabled(fn (ProductCollection $record): bool => ! ProductCollectionResource::canEdit($record)),
                ToggleColumn::make('is_active')
                    ->label('Активна')
                    ->sortable()
                    ->disabled(fn (ProductCollection $record): bool => ! ProductCollectionResource::canEdit($record)),
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('limit')
                    ->label('Лимит товаров')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Товаров в подборке')
                    ->counts('products')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc');
    }
}
