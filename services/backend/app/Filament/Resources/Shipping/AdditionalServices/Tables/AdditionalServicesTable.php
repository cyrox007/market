<?php

namespace App\Filament\Resources\Shipping\AdditionalServices\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdditionalServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('icon')
                    ->label('Иконка')
                    ->formatStateUsing(fn ($state) => $state ? "<i class='{$state} text-xl'></i>" : '—')
                    ->html(),

                TextColumn::make('price_type')
                    ->label('Тип цены')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'fixed' => 'Фиксированная',
                        'from' => 'От X',
                        'custom' => 'Отдельно',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'fixed' => 'success',
                        'from' => 'warning',
                        'custom' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('base_price')
                    ->label('Базовая цена')
                    ->money('RUB')
                    ->default('—')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Сортировка')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('sort_order');
    }
}
