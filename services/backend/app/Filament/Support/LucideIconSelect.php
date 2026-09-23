<?php

namespace App\Filament\Support;

use App\Support\LucideIconRegistry;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;

final class LucideIconSelect
{
    public static function make(string $field = 'icon'): Select
    {
        return Select::make($field)
            ->options(LucideIconRegistry::options())
            ->searchable()
            ->preload()
            ->nullable()
            ->rules([
                'nullable',
                Rule::in(LucideIconRegistry::values()),
            ]);
    }
}
