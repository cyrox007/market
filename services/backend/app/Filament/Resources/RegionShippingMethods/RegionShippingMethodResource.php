<?php

namespace App\Filament\Resources\RegionShippingMethods;

use App\Filament\Resources\RegionShippingMethods\Pages\CreateRegionShippingMethod;
use App\Filament\Resources\RegionShippingMethods\Pages\EditRegionShippingMethod;
use App\Filament\Resources\RegionShippingMethods\Pages\ListRegionShippingMethods;
use App\Filament\Resources\RegionShippingMethods\Pages\ViewRegionShippingMethod;
use App\Filament\Resources\RegionShippingMethods\Schemas\RegionShippingMethodForm;
use App\Filament\Resources\RegionShippingMethods\Schemas\RegionShippingMethodInfolist;
use App\Filament\Resources\RegionShippingMethods\Tables\RegionShippingMethodsTable;
use App\Models\Shipping\RegionShippingMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RegionShippingMethodResource extends Resource
{
    protected static ?string $model = RegionShippingMethod::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return RegionShippingMethodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegionShippingMethodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegionShippingMethodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegionShippingMethods::route('/'),
            'create' => CreateRegionShippingMethod::route('/create'),
            'view' => ViewRegionShippingMethod::route('/{record}'),
            'edit' => EditRegionShippingMethod::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Методы доставки по регионам';
    }

    public static function getModelLabel(): string
    {
        return 'Метод доставки по региону';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Методы доставки по регионам';
    }
}
