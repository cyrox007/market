<?php

namespace App\Filament\Resources\Products\ProductRegionRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductRegionRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->product 
                        ? \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record->product])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('Все товары')
                    ->weight('bold'),

                TextColumn::make('variant.name')
                    ->label('Торговое предложение')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->variant 
                        ? \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record->variant])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('Все предложения'),

                TextColumn::make('region.name')
                    ->label('Локация доставки')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function ($record) {
                        if (!$record->region) {
                            return '—';
                        }
                        $typeLabel = match($record->region->type) {
                            'federal_district' => 'ФО',
                            'region' => 'Регион',
                            'locality' => 'Город',
                            default => '',
                        };
                        $path = $record->region->getFullPathAttribute();
                        return $path . ($typeLabel ? " ({$typeLabel})" : '');
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('price_override')
                    ->label('Переопределение цены')
                    ->money('RUB')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('price_modifier')
                    ->label('Модификатор цены')
                    ->formatStateUsing(function ($record) {
                        if (!$record->price_modifier_type || $record->price_modifier_value === null) {
                            return '—';
                        }

                        $type = match ($record->price_modifier_type) {
                            'fixed' => '₽',
                            'percent' => '%',
                            'multiply' => '×',
                            default => '',
                        };

                        $sign = $record->price_modifier_type === 'fixed' && $record->price_modifier_value > 0 ? '+' : '';
                        return $sign . $record->price_modifier_value . ' ' . $type;
                    }),

                TextColumn::make('is_hidden')
                    ->label('Скрыт')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? 'Да' : 'Нет')
                    ->badge()
                    ->color(fn ($state) => $state ? 'danger' : 'success'),

                TextColumn::make('delivery_days_override')
                    ->label('Срок доставки')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' дн.' : '—')
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                BooleanColumn::make('is_active')
                    ->label('Активно')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создано')
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
                SelectFilter::make('is_hidden')
                    ->label('Видимость')
                    ->options([
                        1 => 'Скрытые',
                        0 => 'Видимые',
                    ]),
                SelectFilter::make('shipping_location_id')
                    ->label('Локация доставки')
                    ->relationship('region', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('type')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $typeLabel = match($record->type) {
                            'federal_district' => 'ФО',
                            'region' => 'Регион',
                            'locality' => 'Город',
                            default => '',
                        };
                        $path = $record->getFullPathAttribute();
                        return $path . ($typeLabel ? " ({$typeLabel})" : '');
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('priority', 'desc')
            ->recordActions([
                EditAction::make(),
                ViewAction::make()
                    ->url(fn ($record) => $record->product 
                        ? \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record->product])
                        : null)
                    ->label('Перейти к товару')
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
