<?php

namespace App\Filament\Resources\Sliders\Tables;

use App\Models\Page\Slider;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SlidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('placement')
                    ->label('Блок')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Slider::placementLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Slider::PLACEMENT_TOP => 'primary',
                        Slider::PLACEMENT_BOTTOM => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('slot')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Slider $record): string =>
                        $record->placement === Slider::PLACEMENT_BOTTOM
                            ? 'Широкий баннер'
                            : (Slider::slotLabels()[$state] ?? $state)
                    )
                    ->toggleable(),

                TextColumn::make('title')
                    ->label(__('filament/admin_sv/slider_resource.title'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('badge_text')
                    ->label('Метка')
                    ->limit(36)
                    ->toggleable(),

                TextColumn::make('priority')
                    ->label(__('filament/admin_sv/slider_resource.priority'))
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('filament/admin_sv/slider_resource.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('filament/admin_sv/slider_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('placement')
                    ->label('Блок главной')
                    ->options(Slider::placementLabels()),

                SelectFilter::make('slot')
                    ->label('Роль в верхнем блоке')
                    ->options(Slider::slotLabels()),

                SelectFilter::make('is_active')
                    ->label('Активен')
                    ->options([
                        1 => 'Активные',
                        0 => 'Неактивные',
                    ]),
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
