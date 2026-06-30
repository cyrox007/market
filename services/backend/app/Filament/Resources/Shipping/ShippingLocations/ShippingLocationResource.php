<?php

namespace App\Filament\Resources\Shipping\ShippingLocations;

use App\Filament\Resources\Shipping\ShippingLocations\Pages\CreateShippingLocation;
use App\Filament\Resources\Shipping\ShippingLocations\Pages\EditShippingLocation;
use App\Filament\Resources\Shipping\ShippingLocations\Pages\ListShippingLocations;
use App\Filament\Resources\Shipping\ShippingLocations\RelationManagers;
use App\Filament\Resources\Shipping\ShippingLocations\Schemas\ShippingLocationForm;
use App\Filament\Resources\Shipping\ShippingLocations\Tables\ShippingLocationsTable;
use App\Models\Shipping\ShippingLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShippingLocationResource extends Resource
{
    protected static ?string $model = ShippingLocation::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\ShippingLocationPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    public static function form(Schema $schema): Schema
    {
        return ShippingLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Shipping\ShippingLocations\RelationManagers\DeliveryHandlingTypesRelationManager::class,
            \App\Filament\Resources\Shipping\ShippingLocations\RelationManagers\CarriersRelationManager::class,
            \App\Filament\Resources\Shipping\ShippingLocations\RelationManagers\PaymentMethodsRelationManager::class,
            \App\Filament\Resources\Shipping\ShippingLocations\RelationManagers\AdditionalServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingLocations::route('/'),
            'create' => CreateShippingLocation::route('/create'),
            'edit' => EditShippingLocation::route('/{record}/edit'),
        ];
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/shipping_location_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/shipping_location_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/shipping_location_resource.plural_model_label');
    }



}