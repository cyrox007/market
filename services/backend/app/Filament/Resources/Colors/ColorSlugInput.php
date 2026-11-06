<?php

namespace App\Filament\Resources\Colors;

use Filament\Forms\Components\TextInput;

/** Поле «ЧПУ» справочников цветов; пустое значение модель заполняет из названия. */
class ColorSlugInput
{
    public static function make(): TextInput
    {
        return TextInput::make('slug')
            ->label('ЧПУ')
            ->helperText(ColorFormHint::make('Пусто — создаётся из названия. Используется в адресах фильтров и выбора цвета: после смены старые ссылки перестанут работать.'))
            ->unique(ignoreRecord: true)
            ->maxLength(255);
    }
}
