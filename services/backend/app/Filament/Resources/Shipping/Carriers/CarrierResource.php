<?php

namespace App\Filament\Resources\Shipping\Carriers;

use App\Filament\Resources\Shipping\Carriers\Pages\CreateCarrier;
use App\Filament\Resources\Shipping\Carriers\Pages\EditCarrier;
use App\Filament\Resources\Shipping\Carriers\Pages\ListCarriers;
use App\Filament\Resources\Shipping\Carriers\Schemas\CarrierForm;
use App\Filament\Resources\Shipping\Carriers\Tables\CarriersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use App\Models\Shipping\Carrier;

class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\CarrierPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    public static function form(Schema $schema): Schema
    {
        return CarrierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CarriersTable::configure($table);
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
            'index' => ListCarriers::route('/'),
            'create' => CreateCarrier::route('/create'),
            'edit' => EditCarrier::route('/{record}/edit'),
        ];
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/carrier_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/carrier_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/carrier_resource.plural_model_label');
    }



}
