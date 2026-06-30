<?php

namespace App\Filament\Resources\Shipping\ShippingLocations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShippingLocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/shipping_location_resource.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('filament/admin_sv/shipping_location_resource.type'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'federal_district' => 'primary',
                        'region' => 'success',
                        'locality' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'federal_district' => 'Федеральный округ',
                        'region' => 'Регион',
                        'locality' => 'Населенный пункт',
                        default => $state,
                    }),

                TextColumn::make('parent.name')
                    ->label(__('filament/admin_sv/shipping_location_resource.parent.name'))
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                TextColumn::make('code')
                    ->label(__('filament/admin_sv/shipping_location_resource.code'))
                    ->searchable(),

                TextColumn::make('delivery_price')
                    ->label(__('filament/admin_sv/shipping_location_resource.delivery_price'))
                    ->money('RUB')
                    ->default('—')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label(__('filament/admin_sv/shipping_location_resource.is_active'))
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('filament/admin_sv/shipping_location_resource.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип локации')
                    ->options([
                        'federal_district' => 'Федеральный округ',
                        'region' => 'Регион',
                        'locality' => 'Населенный пункт',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Активность')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
            ])
            ->defaultSort('sort_order')
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
