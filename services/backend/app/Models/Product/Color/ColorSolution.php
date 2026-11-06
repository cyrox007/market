<?php

namespace App\Models\Product\Color;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Цветовое решение — то, что видит покупатель: название, пиктограмма, базовые цвета.
 */
class ColorSolution extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(fn (self $solution) => $solution->slug = ColorSlug::unique(static::class, $solution, $solution->slug ?: $solution->name));
        static::saved(fn () => Product::flushAllProductCaches());
        static::deleted(fn () => Product::flushAllProductCaches());
    }

    public function baseColors(): BelongsToMany
    {
        return $this->belongsToMany(BaseColor::class, 'color_solution_base_color')
            ->orderBy('base_colors.sort_order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductColorRow::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 100, 100)
            ->optimize()
            ->performOnCollections('icon');
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('icon') ?: null;
    }
}
