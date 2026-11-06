<?php

namespace App\Models\Product\Color;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Базовый цвет — справочник для поиска.
 */
class BaseColor extends Model
{
    protected $fillable = ['name', 'slug', 'hex', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::saving(fn (self $color) => $color->slug = ColorSlug::unique(static::class, $color, $color->slug ?: $color->name));
        static::saved(fn () => Product::flushAllProductCaches());
        static::deleted(fn () => Product::flushAllProductCaches());
    }

    public function solutions(): BelongsToMany
    {
        return $this->belongsToMany(ColorSolution::class, 'color_solution_base_color');
    }
}
