<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Select;

class DirectorySelectLoading
{
    public static function apply(Select $field, string $noun, array $parents = []): Select
    {
        return $field->preload()->loadingMessage('Загружаем '.$noun.'…')
            ->searchingMessage('Ищем '.$noun.'…')->searchDebounce(400)
            ->belowContent(function (Select $component) use ($noun, $parents) {
                $path = $component->getStatePath();
                $prefix = substr($path, 0, strrpos($path, '.') + 1);

                return view('filament.forms.directory-loading', [
                    'targets' => implode(',', array_map(fn (string $parent): string => $prefix.$parent, $parents)),
                    'message' => 'Загружаем '.$noun.'…',
                ]);
            });
    }
}
