<?php

namespace App\Filament\Resources\ProductBlocks\FeatureBlocks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductFeatureBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('subtitle')
                    ->label('Подзаголовок')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                SpatieMediaLibraryImageColumn::make('icon')
                    ->collection('icon')
                    ->label('Изображение')
                    ->conversion('thumb')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->icon ? null : null),
                TextColumn::make('icon')
                    ->label('Иконка (класс)')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('icon_color')
                    ->label('Цвет иконки')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ?: 'gray-600'),
                TextColumn::make('bg_color')
                    ->label('Цвет фона')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ?: 'gray-100'),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('categories_count')
                    ->label('Категорий')
                    ->counts('categories')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Активен')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
