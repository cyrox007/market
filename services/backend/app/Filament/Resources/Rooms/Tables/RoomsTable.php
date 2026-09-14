<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Подгружаем родителя: колонка «Название» строит отступ по цепочке предков.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent'))
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($record, $state) {
                        $level = 0;
                        $room = $record;
                        while ($room && $room->parent_id) {
                            $level++;
                            $room = $room->parent;
                        }
                        return str_repeat('— ', $level) . $state;
                    }),
                TextColumn::make('parent.name')
                    ->label('Родительская')
                    ->placeholder('Корневая')
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label('URL')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL скопирован'),
                TextColumn::make('product_categories_count')
                    ->label('Категорий')
                    ->counts('productCategories')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Родительская комната')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Активна')
                    ->options([1 => 'Активные', 0 => 'Неактивные']),
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
            ->defaultSort('priority', 'asc');
    }
}
