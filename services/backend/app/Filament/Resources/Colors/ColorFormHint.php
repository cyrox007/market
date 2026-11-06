<?php

namespace App\Filament\Resources\Colors;

use Illuminate\Support\HtmlString;

/** Подсказка под полем справочника цветов: курсивом, мельче основного текста. */
class ColorFormHint
{
    public static function make(string $text): HtmlString
    {
        return new HtmlString('<span style="font-style: italic; font-size: 0.75rem; line-height: 1rem;">' . e($text) . '</span>');
    }
}
