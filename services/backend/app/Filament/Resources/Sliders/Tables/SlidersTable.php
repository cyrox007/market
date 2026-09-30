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
                        Slider::PLACEMENT_HOME_HERO => 'primary',
                        Slider::PLACEMENT_HOME_CATEGORIES => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('filament/admin_sv/slider_resource.title'))
                    ->formatStateUsing(fn ($state, Slider $record): string => $record->display_title)
                    ->description(fn (Slider $record): ?string => $record->category?->name)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('link')
                    ->label(__('filament/admin_sv/slider_resource.link'))
                    ->formatStateUsing(fn ($state, Slider $record): ?string => $record->display_link)
                    ->searchable()
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
