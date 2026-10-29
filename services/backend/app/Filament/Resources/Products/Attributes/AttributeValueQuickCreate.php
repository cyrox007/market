<?php

namespace App\Filament\Resources\Products\Attributes;

use App\Models\Product\AttributeValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttributeValueQuickCreate
{
    public static function create(array $data, mixed $attributeId): int
    {
        if (! array_key_exists('value', $data)) {
            Log::warning('Attribute value dialog submitted without value', [
                'attribute_id' => is_scalar($attributeId) ? $attributeId : null,
                'submitted_fields' => array_keys($data),
            ]);
        }

        if (is_string($data['value'] ?? null)) {
            $data['value'] = trim($data['value']);
        }
        $validated = Validator::make([...$data, 'attribute_id' => $attributeId], [
            'attribute_id' => ['required', 'integer', 'exists:product_attributes,id'],
            'value' => ['required', 'string', 'max:255'],
            'color_code' => ['nullable', 'string', 'max:32'],
        ], [
            'value.required' => 'Введите значение характеристики. Если окно сбросилось, откройте его заново.',
            'attribute_id.required' => 'Сначала выберите характеристику.',
            'attribute_id.exists' => 'Характеристика больше недоступна. Выберите её заново.',
        ])->validate();

        $base = Str::slug($validated['value']) ?: (string) Str::uuid();
        $slug = $base;
        $i = 2;
        while (AttributeValue::where('attribute_id', $validated['attribute_id'])->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return AttributeValue::create([
            'attribute_id' => $validated['attribute_id'],
            'value' => $validated['value'],
            'slug' => $slug,
            'color_code' => $validated['color_code'] ?? null,
        ])->id;
    }
}
