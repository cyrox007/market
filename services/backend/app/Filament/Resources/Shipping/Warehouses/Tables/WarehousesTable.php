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

                TextColumn::make('address')->label('Адрес')->wrap()->limit(100),
                IconColumn::make('has_coordinates')->label('Точка склада')
                    ->state(fn ($record): bool => $record->latitude !== null && $record->longitude !== null)
                    ->boolean()->tooltip('Обе координаты заданы — можно рассчитывать расстояние'),

                TextColumn::make('source_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manufacturer' => 'Фабрика',
                        'supplier' => 'Поставщик',
                        default => 'Склад',
                    })
                    ->badge(),

                TextColumn::make('delivery_profiles_count')
                    ->label('Профилей доставки')
                    ->counts('deliveryProfiles')
                    ->sortable(),

                TextColumn::make('shipping_locations_count')
                    ->label('Локаций')
                    ->counts('shippingLocations')
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
