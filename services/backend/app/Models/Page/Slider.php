<?php

namespace App\Models\Page;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use App\Models\Product\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const PLACEMENT_HOME_HERO = 'home_hero';

    public const PLACEMENT_HOME_CATEGORIES = 'home_categories';

    protected $fillable = [
        'placement',
        'category_id',
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
        'category_id' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();

        static::saving(function (Slider $slider): void {
            if (
                $slider->placement === self::PLACEMENT_HOME_CATEGORIES
                && $slider->category_id
                && blank($slider->title)
            ) {
                $slider->title = (string) Category::query()->find($slider->category_id)?->name;
            }
        });
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
     * @return array<string, string>
     */
    public static function placementLabels(): array
    {
        return [
            self::PLACEMENT_HOME_HERO => 'Главный промо-слайдер',
            self::PLACEMENT_HOME_CATEGORIES => 'Карусель категорий',
        ];
    }

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getDisplayTitleAttribute(): string
    {
        if ($this->placement === self::PLACEMENT_HOME_CATEGORIES && $this->category) {
            return (string) $this->category->name;
        }

        return (string) $this->title;
    }

    public function getDisplayLinkAttribute(): ?string
    {
        if ($this->placement === self::PLACEMENT_HOME_CATEGORIES && $this->category) {
            return $this->category->full_path;
        }

        return $this->link;
    }

    public function getDisplayImageMedia(): ?Media
    {
        $media = $this->getFirstMedia('image');

        if ($media) {
            return $media;
        }

        if ($this->placement === self::PLACEMENT_HOME_CATEGORIES && $this->category) {
            return $this->category->getFirstMedia('image')
                ?: $this->category->getFirstMedia('images');
        }

        return null;
    }

    public function getDisplayMobileImageMedia(): ?Media
    {
        return $this->getFirstMedia('mobile_image') ?: $this->getDisplayImageMedia();
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

        $this->addMediaCollection('mobile_image')
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
            ->performOnCollections('image', 'mobile_image');

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


