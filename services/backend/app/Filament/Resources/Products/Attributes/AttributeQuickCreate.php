<?php

namespace App\Filament\Resources\Products\Attributes;

use App\Models\Product\Attribute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;

/**
 * Создание характеристики прямо из выпадающего списка выбора.
 */
class AttributeQuickCreate
{
    public static function form(): array
    {
        return [
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            Select::make('type')
                ->label('Тип')
                ->options([
                    'text' => 'Текст',
                    'select' => 'Список',
                    'color' => 'Цвет',
                    'number' => 'Число',
                ])
                ->default('text')
                ->required(),
            Toggle::make('is_use_in_variations')->label('Участвует в вариациях')->default(false),
            Toggle::make('is_filterable')->label('Показывать в фильтрах')->default(false),
            Toggle::make('is_multiple')->label('Множественный выбор')->default(false),
            Toggle::make('allow_custom_value')->label('Разрешить ручной ввод')->default(false),
        ];
    }

    public static function create(array $data): int
    {
        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 2;
        while (Attribute::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return Attribute::create([
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'] ?? 'text',
            'is_use_in_variations' => (bool) ($data['is_use_in_variations'] ?? false),
            'is_filterable' => (bool) ($data['is_filterable'] ?? false),
            'is_multiple' => (bool) ($data['is_multiple'] ?? false),
            'allow_custom_value' => (bool) ($data['allow_custom_value'] ?? false),
        ])->id;
    }
}
