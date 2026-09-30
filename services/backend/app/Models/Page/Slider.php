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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Slider extends Model implements HasMedia, PageableContract
{
    use Cacheable, HasFactory, InteractsWithMedia, Pageable, HasSEO, MetaUniversalSEO;

    public const ROOT_PATH = '/slider';

    public const ACTIVE = true;

    public const PLACEMENT_TOP = 'top';

    public const PLACEMENT_BOTTOM = 'bottom';

    public const SLOT_MAIN = 'main';

    public const SLOT_RIGHT_TOP = 'right_top';

    public const SLOT_RIGHT_BOTTOM = 'right_bottom';

    protected $fillable = [
        'placement',
        'slot',
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
        'badge_tone',
        'image_url',
        'mobile_image_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();

        static::saving(function (Slider $slider): void {
            if ($slider->placement === self::PLACEMENT_BOTTOM) {
                // У нижнего блока нет дополнительных позиций.
                $slider->slot = self::SLOT_MAIN;
            }

            // Правая верхняя и правая нижняя зоны — фиксированные места макета.
            // В каждой из них одновременно может быть только один активный материал.
            if (
                $slider->placement === self::PLACEMENT_TOP
                && in_array($slider->slot, [self::SLOT_RIGHT_TOP, self::SLOT_RIGHT_BOTTOM], true)
                && $slider->is_active
            ) {
                $query = static::query()
                    ->where('placement', self::PLACEMENT_TOP)
                    ->where('slot', $slider->slot)
                    ->where('is_active', true);

                if ($slider->exists) {
                    $query->where($slider->getKeyName(), '!=', $slider->getKey());
                }

                $query->update(['is_active' => false]);
            }
        });
    }

    protected static function newFactory()
    {
        return \Database\Factories\SliderFactory::new();
    }

    public static function getRootPath(): string
    {
        return self::ROOT_PATH;
    }

    public static function getActiveConstant(): bool
    {
        return self::ACTIVE;
    }

    /**
     * @return array<string,string>
     */
    public static function placementLabels(): array
    {
        return [
            self::PLACEMENT_TOP => 'Верхний блок главной',
            self::PLACEMENT_BOTTOM => 'Нижний широкий баннер',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function slotLabels(): array
    {
        return [
            self::SLOT_MAIN => 'Большой слайдер слева',
            self::SLOT_RIGHT_TOP => 'Маленькая карточка справа — сверху',
            self::SLOT_RIGHT_BOTTOM => 'Маленькая карточка справа — снизу',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function badgeToneLabels(): array
    {
        return [
            'red' => 'Красный',
            'yellow' => 'Жёлтый',
            'green' => 'Зелёный',
        ];
    }

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function scopeSlot(Builder $query, string $slot): Builder
    {
        return $query->where('slot', $slot);
    }

    public static function getActiveFieldName(): string
    {
        return 'is_active';
    }

    public static function getSortFieldName(): string
    {
        return 'priority';
    }

    public function getFullPathAttribute(): ?string
    {
        if (! $this->slug) {
            return null;
        }

        return self::ROOT_PATH . '/' . $this->slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('mobile_image')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->fit(Fit::Crop, 300, 300)
            ->optimize()
            ->performOnCollections('image', 'mobile_image');

        $this->addMediaConversion('fullhd')
            ->width(1920)
            ->height(1080)
            ->fit(Fit::Contain, 1920, 1080)
            ->optimize()
            ->performOnCollections('image', 'mobile_image');

        $this->addMediaConversion('hd')
            ->width(1280)
            ->height(720)
            ->fit(Fit::Contain, 1280, 720)
            ->optimize()
            ->performOnCollections('image', 'mobile_image');
    }
}
