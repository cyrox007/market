<?php

namespace App\Filament\Resources\Shipping\AdditionalServices;

use App\Filament\Resources\Shipping\AdditionalServices\Pages\CreateAdditionalService;
use App\Filament\Resources\Shipping\AdditionalServices\Pages\EditAdditionalService;
use App\Filament\Resources\Shipping\AdditionalServices\Pages\ListAdditionalServices;
use App\Filament\Resources\Shipping\AdditionalServices\Schemas\AdditionalServiceForm;
use App\Filament\Resources\Shipping\AdditionalServices\Tables\AdditionalServicesTable;
use App\Models\Shipping\AdditionalService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class AdditionalServiceResource extends Resource
{
    protected static ?string $model = AdditionalService::class;

    protected static ?string $navigationLabel = 'Дополнительные услуги';

    protected static ?string $modelLabel = 'Дополнительная услуга';

    protected static ?string $pluralModelLabel = 'Дополнительные услуги';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Доставка';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return AdditionalServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdditionalServicesTable::configure($table);
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
            'index' => ListAdditionalServices::route('/'),
            'create' => CreateAdditionalService::route('/create'),
            'edit' => EditAdditionalService::route('/{record}/edit'),
        ];
    }
}
