<?php

namespace App\Filament\Resources\Payment\Payments;

use App\Filament\Resources\Payment\Payments\Pages\ListPayments;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
use Vanilo\Payment\Models\Payment;

/**
 * Ресурс для списка платежей (транзакции по заказам).
 * Отдельно от PaymentMethod — это способы оплаты (card_ecom, cash и т.д.).
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Платежи';

    protected static ?string $modelLabel = 'Платёж';

    protected static ?string $pluralModelLabel = 'Платежи';

    protected static ?string $recordTitleAttribute = 'hash';

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Payment\Payments\Tables\PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Платежи';
    }
}
