<?php

namespace App\Models\Product\Color;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ColorSlug
{
    /** @param  class-string<Model>  $class */
    public static function unique(string $class, Model $model, string $source): string
    {
        $base = Str::slug(str_replace('/', ' ', $source)) ?: 'color';
        $slug = $base;
        $i = 2;
        while ($class::query()->where('slug', $slug)->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
