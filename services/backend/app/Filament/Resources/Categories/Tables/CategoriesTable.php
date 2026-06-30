<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/category_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($record, $state) {
                        $level = 0;
                        $category = $record;
                        while ($category && $category->parent_id) {
                            $level++;
                            $category = $category->parent;
                        }
                        $indent = str_repeat('— ', $level);
                        return $indent . $state;
                    }),
                TextColumn::make('parent.name')
                    ->label(__('filament/admin_sv/category_resource.parent.name'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('Корневая')
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label(__('filament/admin_sv/category_resource.slug'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL скопирован'),
                TextColumn::make('products_count')
                    ->label(__('filament/admin_sv/category_resource.products_count'))
                    ->counts('products')
                    ->sortable(),
                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/category_resource.priority'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin_sv/category_resource.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/category_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Родительская категория')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Активна')
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
            ->defaultSort('priority', 'asc');
    }
}
