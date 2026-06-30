<?php

namespace App\Filament\Resources\News\Tables;

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
                TextColumn::make('title')
                    ->label(__('filament/admin_sv/category_resource.title'))
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
                TextColumn::make('parent.title')
                    ->label(__('filament/admin_sv/category_resource.parent.title'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('Корневая')
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label(__('filament/admin_sv/category_resource.slug'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL скопирован'),
                TextColumn::make('articles_count')
                    ->label(__('filament/admin_sv/category_resource.articles_count'))
                    ->counts('articles')
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
                    ->relationship('parent', 'title')
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


