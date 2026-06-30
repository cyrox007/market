<?php

namespace App\Models\News;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class Article extends Model implements HasMedia, PageableContract
{
    use Cacheable, HasFactory, InteractsWithMedia, Pageable, HasSEO, MetaUniversalSEO;

    public const ROOT_PATH = '/news';

    public const ACTIVE = true;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'published_at',
        'is_active',
        'priority',
        'author_id',
        'category_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'published_at' => 'datetime',
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
        return \Database\Factories\ArticleFactory::new();
    }

    /**
     * Get the author of the article.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the category of the article.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /**
     * Scope a query to only include published articles.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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
     * Get full URL path for the article.
     */
    public function getFullPathAttribute(): ?string
    {
        if (!$this->slug) {
            return null;
        }

        // Загружаем category если она не загружена
        if (!$this->relationLoaded('category') && $this->category_id) {
            $this->load('category');
        }

        if ($this->category && $this->category->full_path) {
            $categoryPath = ltrim($this->category->full_path, '/');
            return '/' . $categoryPath . '/' . $this->slug;
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
            ->singleFile(); // Для главного изображения

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
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
            ->performOnCollections('image', 'gallery');

        // Конверсия для основного изображения (Full HD)
        $this->addMediaConversion('fullhd')
            ->width(1920)
            ->height(1080)
            ->fit(Fit::Contain, 1920, 1080)
            ->optimize()
            ->performOnCollections('image', 'gallery');

        // Конверсия для среднего размера (HD)
        $this->addMediaConversion('hd')
            ->width(1280)
            ->height(720)
            ->fit(Fit::Contain, 1280, 720)
            ->optimize()
            ->performOnCollections('image', 'gallery');
    }
}

