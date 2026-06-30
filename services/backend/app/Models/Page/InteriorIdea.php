<?php

namespace App\Models\Page;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class InteriorIdea extends Model implements HasMedia, PageableContract
{
    use Cacheable, HasFactory, InteractsWithMedia, Pageable;

    protected $fillable = [
        'title',
        'is_active',
        'priority',
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

        // Каскадное удаление хотспотов при удалении идеи
        static::deleting(function ($idea) {
            $idea->hotspots()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\InteriorIdeaFactory::new();
    }

    public const ACTIVE = true;

    /**
     * Get root path constant.
     */
    public static function getRootPath(): string
    {
        return '';
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
     * Сбросить кэш API-списка идей для интерьера (GET /api/v1/interior-ideas).
     */
    public static function flushListCache(): void
    {
        Cache::forget(static::cacheKey('index'));

        if (static::supportsCacheTags()) {
            try {
                Cache::tags(static::getCacheTags())->flush();
            } catch (\Exception) {
                // драйвер без тегов — ключ index уже сброшен выше
            }
        }
    }

    /**
     * Get full URL path for the interior idea.
     */
    public function getFullPathAttribute(): ?string
    {
        // InteriorIdeas не имеют отдельных страниц, они отображаются только на главной
        return null;
    }

    /**
     * Get hotspots for this interior idea.
     */
    public function hotspots()
    {
        return $this->hasMany(InteriorIdeaHotspot::class)->orderBy('priority');
    }

    /**
     * Backward-compatible alias for older MediaLibrary API usage in tests.
     */
    public function addMediaFromFile(string $path)
    {
        return $this->addMedia($path);
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

        // Конверсия для основного изображения
        $this->addMediaConversion('main')
            ->width(820)
            ->height(880)
            ->fit(Fit::Contain, 820, 880)
            ->optimize()
            ->performOnCollections('image');
    }
}
