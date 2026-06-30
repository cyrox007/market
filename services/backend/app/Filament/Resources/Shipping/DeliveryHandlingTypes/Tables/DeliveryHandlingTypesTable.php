<?php

namespace App\Filament\Resources\Shipping\DeliveryHandlingTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeliveryHandlingTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.code'))
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.description'))
                    ->limit(50)
                    ->tooltip(fn($record) => $record->description),

                TextColumn::make('requires_floor')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.requires_floor')),

                TextColumn::make('max_floor')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.max_floor'))
                    ->sortable()
                    ->default('—'),

                TextColumn::make('requires_elevator')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.requires_elevator')),

                TextColumn::make('is_active')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.is_active')),

                TextColumn::make('sort_order')
                    ->label(__('filament/admin_sv/delivery_handling_type_resource.sort_order')),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Активность')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),

                SelectFilter::make('requires_elevator')
                    ->label('Требуется лифт')
                    ->options([
                        1 => 'Да',
                        0 => 'Нет',
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
