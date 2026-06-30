<?php

namespace App\Filament\Resources\RegionShippingMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegionShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['region', 'shippingMethod.carrier']);
            })
            ->columns([
                TextColumn::make('region.name')
                    ->label('Локация доставки')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $region = $record->region;
                        if (!$region) {
                            return '—';
                        }
                        $typeLabel = match($region->type) {
                            'federal_district' => 'ФО',
                            'region' => 'Регион',
                            'locality' => 'Город',
                            default => '',
                        };
                        $path = $region->getFullPathAttribute();
                        return $path . ($typeLabel ? " ({$typeLabel})" : '');
                    })
                    ->weight('bold'),

                TextColumn::make('shippingMethod.name')
                    ->label('Метод доставки')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $method = $record->shippingMethod;
                        if (!$method) {
                            return '—';
                        }
                        $carrier = $method->carrier;
                        $carrierName = $carrier ? " ({$carrier->name})" : '';
                        return $method->name . $carrierName;
                    }),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Активность')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
