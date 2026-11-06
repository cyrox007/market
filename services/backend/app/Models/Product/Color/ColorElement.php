<?php

namespace App\Models\Product\Color;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Элемент товара со своим цветом.
 */
class ColorElement extends Model
{
    protected $fillable = ['name', 'slug', 'filter_name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::saving(fn (self $element) => $element->slug = ColorSlug::unique(static::class, $element, $element->slug ?: $element->name));
        static::saved(fn () => Product::flushAllProductCaches());
        static::deleted(fn () => Product::flushAllProductCaches());
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductColorRow::class);
    }
}
