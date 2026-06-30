<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/store_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('city')
                    ->label(__('filament/admin_sv/store_resource.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label(__('filament/admin_sv/store_resource.address'))
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label(__('filament/admin_sv/store_resource.phone'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('hours')
                    ->label(__('filament/admin_sv/store_resource.hours'))
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/store_resource.priority'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin_sv/store_resource.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/store_resource.created_at'))
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
                SelectFilter::make('city')
                    ->label('Город')
                    ->options(function () {
                        return \App\Models\Page\Store::query()
                            ->distinct()
                            ->pluck('city', 'city')
                            ->toArray();
                    }),
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
