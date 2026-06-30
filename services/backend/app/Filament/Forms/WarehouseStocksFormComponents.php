<?php

namespace App\Filament\Forms;

use App\Models\Inventory\Warehouse;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class WarehouseStocksFormComponents
{
    public static function warehouseSelectField(): Select
    {
        return Select::make('warehouse_id')
            ->label('Склад (1С)')
            ->options(fn (): array => Warehouse::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function (Warehouse $warehouse): array {
                    $label = $warehouse->name;
                    if (filled($warehouse->external_id)) {
                        $label .= ' · 1С: ' . Str::limit($warehouse->external_id, 12, '…');
                    }

                    return [$warehouse->id => $label];
                })
                ->all())
            ->searchable()
            ->preload()
            ->required()
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->helperText('Склады из справочника 1С подтягиваются автоматически при синхронизации остатков.');
    }

    public static function warehouseStocksRepeater(string $relationship = 'warehouseStocks'): Repeater
    {
        return Repeater::make($relationship)
            ->label('Остатки по складам')
            ->relationship($relationship)
            ->schema([
                self::warehouseSelectField(),
                TextInput::make('quantity')
                    ->label('Количество')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0),
            ])
            ->columns(2)
            ->addActionLabel('Добавить склад')
            ->defaultItems(0)
            ->columnSpanFull();
    }
}
