<?php

namespace App\Models\Page;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class Slider extends Model implements HasMedia, PageableContract
{
    use Cacheable, HasFactory, InteractsWithMedia, Pageable, HasSEO, MetaUniversalSEO;

    public const ROOT_PATH = '/slider';

    public const ACTIVE = true;

    protected $fillable = [
        'title',
        'description',
        'link',
        'button_text',
        'is_active',
        'priority',
        'slug',
        'badge_text',
        'badge_link',
        'badge_icon',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\SliderFactory::new();
    }

    /**
     * Get root path constant.
     */
    public static function getRootPath(): string
    {
        return self::ROOT_PATH;
    }

    /**
     * Get active status constant.
     */
    public static function getActiveConstant(): bool
    {
        return self::ACTIVE;
    }

    /**
     * Get the name of the active field.
     */
    public static function getActiveFieldName(): string
    {
        return 'is_active';
    }

    /**
     * Get the name of the sort field.
     */
    public static function getSortFieldName(): string
    {
        return 'priority';
    }

    /**
     * Get full URL path for the slider.
     */
    public function getFullPathAttribute(): ?string
    {
        if (!$this->slug) {
            return null;
        }

        return self::ROOT_PATH . '/' . $this->slug;
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Конверсия для миниатюр (thumb)
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->fit(Fit::Crop, 300, 300)
            ->optimize()
            ->performOnCollections('image');

        // Конверсия для основного изображения (Full HD)
        $this->addMediaConversion('fullhd')
            ->width(1920)
            ->height(1080)
            ->fit(Fit::Contain, 1920, 1080)
            ->optimize()
            ->performOnCollections('image');

        // Конверсия для среднего размера (HD)
        $this->addMediaConversion('hd')
            ->width(1280)
            ->height(720)
            ->fit(Fit::Contain, 1280, 720)
            ->optimize()
            ->performOnCollections('image');
    }
}


