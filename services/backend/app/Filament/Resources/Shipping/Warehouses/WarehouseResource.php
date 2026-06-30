<?php

namespace App\Filament\Resources\Shipping\Warehouses;

use App\Filament\Resources\Shipping\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Shipping\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Shipping\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Shipping\Warehouses\Schemas\WarehouseForm;
use App\Filament\Resources\Shipping\Warehouses\Tables\WarehousesTable;
use App\Models\Inventory\Warehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationLabel = 'Склады';

    protected static ?string $modelLabel = 'Склад';

    protected static ?string $pluralModelLabel = 'Склады';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
