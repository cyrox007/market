<?php

namespace App\Filament\Resources\Shipping\Carriers\Tables;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Vanilo\Shipment\Models\ShippingMethod;

class CarriersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/carrier_resource.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shipping_methods_count')
                    ->label(__('filament/admin_sv/carrier_resource.shipping_methods_count'))
                    ->getStateUsing(function ($record) {
                        return ShippingMethod::where('carrier_id', $record->id)->count();
                    })
                    ->sortable(false),

                IconColumn::make('is_active')
                    ->label(__('filament/admin_sv/carrier_resource.is_active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/carrier_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament/admin_sv/carrier_resource.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Активность')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
            ])

            ->defaultSort('name');
    }
}
