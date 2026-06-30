<?php

namespace App\Filament\Resources\Payment\PaymentMethods;

use App\Filament\Resources\Payment\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\Payment\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\Payment\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\Payment\PaymentMethods\Schemas\PaymentMethodForm;
use App\Filament\Resources\Payment\PaymentMethods\Tables\PaymentMethodsTable;
use App\Models\Payment\PaymentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $policy = \App\Policies\PaymentMethodPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodsTable::configure($table);
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
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/payment_method_resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament/admin_sv/payment_method_resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/admin_sv/payment_method_resource.plural_model_label');
    }
}
