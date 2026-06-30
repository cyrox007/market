<?php

namespace App\Filament\Resources\Payment\PaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentMethodsTable
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
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->description),

                TextColumn::make('icon')
                    ->label('Иконка')
                    ->default('—'),

                TextColumn::make('is_active')
                    ->label('На сайте')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->isGloballyActive() ? 'Активен' : 'Отключён')
                    ->color(fn ($state, $record) => $record->isGloballyActive() ? 'success' : 'danger'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создан')
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
