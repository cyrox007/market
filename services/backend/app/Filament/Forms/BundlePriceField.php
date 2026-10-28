<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TextInput;

class BundlePriceField
{
    public static function make(): TextInput
    {
        return TextInput::make('bundle_price')->label('Цена всего комплекта (необязательно)')
            ->numeric()->minValue(0.01)->maxValue(99999999.99)->step(0.01)->rules(['nullable', 'decimal:0,2'])
            ->prefix('₽')->placeholder('Сумма цен составляющих')
            ->helperText('Пусто — сумма актуальных цен частей с учётом количества. Заполнено — цена одного полного комплекта. Для автоматического расчёта очистите поле; ноль недопустим.')
            ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null);
    }
}
