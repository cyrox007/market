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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RegionShippingMethodResource extends Resource
{
    protected static ?string $model = RegionShippingMethod::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

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
        return [];
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
        return 'Устаревшие методы доставки по регионам';
    }

    public static function getModelLabel(): string
    {
        return 'Устаревшая привязка доставки';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Устаревшие привязки доставки';
    }
}
