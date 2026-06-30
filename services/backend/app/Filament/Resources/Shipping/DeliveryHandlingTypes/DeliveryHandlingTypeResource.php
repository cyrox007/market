<?php

namespace App\Filament\Resources\Shipping\DeliveryHandlingTypes;

use App\Filament\Resources\Shipping\DeliveryHandlingTypes\Pages\CreateDeliveryHandlingType;
use App\Filament\Resources\Shipping\DeliveryHandlingTypes\Pages\EditDeliveryHandlingType;
use App\Filament\Resources\Shipping\DeliveryHandlingTypes\Pages\ListDeliveryHandlingTypes;
use App\Filament\Resources\Shipping\DeliveryHandlingTypes\Schemas\DeliveryHandlingTypeForm;
use App\Filament\Resources\Shipping\DeliveryHandlingTypes\Tables\DeliveryHandlingTypesTable;
use App\Models\Shipping\DeliveryHandlingType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeliveryHandlingTypeResource extends Resource
{
    protected static ?string $model = DeliveryHandlingType::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\DeliveryHandlingTypePolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    public static function form(Schema $schema): Schema
    {
        return DeliveryHandlingTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryHandlingTypesTable::configure($table);
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
            'index' => ListDeliveryHandlingTypes::route('/'),
            'create' => CreateDeliveryHandlingType::route('/create'),
            'edit' => EditDeliveryHandlingType::route('/{record}/edit'),
        ];
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/delivery_handling_type_resource.navigation_label');
    }
    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/delivery_handling_type_resource.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/delivery_handling_type_resource.plural_model_label');
    }



}
