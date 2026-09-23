<?php

namespace App\Filament\Resources\Shipping\Warehouses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('external_id')
                    ->label('Внешний ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('delivery_methods_count')
                    ->label('Способов доставки')
                    ->counts('deliveryMethods')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
