<?php

namespace App\Models\Product;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Category\Models\Taxon as VaniloTaxon;
use Vanilo\Category\Models\Taxonomy;
use Vanilo\Product\Models\ProductProxy;
use App\Models\Product\Product;

class Category extends VaniloTaxon implements PageableContract, HasMedia
{
    use Cacheable, HasFactory, Pageable, InteractsWithMedia, HasSEO, MetaUniversalSEO;

    public const ROOT_PATH = '/catalog';

    public const ACTIVE = true;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();

        // Ограничиваем модель своей таксономией (Category → продуктовая, Room → комнаты),
        // иначе вторая таксономия «протекает» в запросы каталога.
        static::addGlobalScope('taxonomy', function (Builder $query) {
            $query->where($query->getModel()->getTable() . '.taxonomy_id', static::taxonomyIdForCurrentClass());
        });

        // Автоматически устанавливаем taxonomy_id при создании категории
        static::creating(function ($category) {
            if (empty($category->taxonomy_id)) {
                $category->taxonomy_id = static::taxonomyIdForCurrentClass();
            }
        });

        // Также устанавливаем при обновлении, если taxonomy_id был удален
        static::updating(function ($category) {
            if (empty($category->taxonomy_id)) {
                $category->taxonomy_id = static::taxonomyIdForCurrentClass();
            }
        });
        
        // Очищаем кеш блоков при изменении связей
        static::saved(function ($category) {
            static::clearCategoryBlocksCache($category->id);
            // Сбрасываем кэш товаров при изменении категории
            // flushCache() уже вызывается автоматически через трейт Cacheable
        });

        // Сбрасываем кэш при удалении категории
        // flushCache() уже вызывается автоматически через трейт Cacheable
    }
    
    /**
     * Очистить кеш блоков для категории
     */
    protected static function clearCategoryBlocksCache(int $categoryId): void
    {
        \Cache::forget("category_with_feature_blocks_{$categoryId}");
        \Cache::forget("category_with_delivery_blocks_{$categoryId}");
        
        // Очищаем кеш всех товаров этой категории
        $products = static::find($categoryId)?->products ?? collect();
        foreach ($products as $product) {
            \Cache::forget("feature_blocks_product_{$product->id}");
            \Cache::forget("delivery_blocks_product_{$product->id}");
        }
    }

    /**
     * Get or create default taxonomy for product categories.
     */
    protected static function getDefaultTaxonomyId(): int
    {
        static $taxonomyId = null;

        if ($taxonomyId !== null && !Taxonomy::where('id', $taxonomyId)->exists()) {
            $taxonomyId = null;
        }

        if ($taxonomyId === null) {
            $taxonomy = Taxonomy::firstOrCreate(
                ['name' => 'Product Categories'],
                [
                    'name' => 'Product Categories',
                    'slug' => 'product-categories'
                ]
            );
            $taxonomyId = $taxonomy->id;
        }

        return $taxonomyId;
    }

    /**
     * Slug таксономии этой модели (Room переопределяет на 'rooms').
     */
    protected static function taxonomySlug(): string
    {
        return 'product-categories';
    }

    protected static function taxonomyName(): string
    {
        return 'Product Categories';
    }

    /** @var array<string, int> */
    protected static array $taxonomyIdCache = [];

    /**
     * ID таксономии текущего класса (late static binding), создаётся при отсутствии.
     */
    protected static function taxonomyIdForCurrentClass(): int
    {
        $slug = static::taxonomySlug();

        // Сбрасываем кэш, если таксономия исчезла (напр. RefreshDatabase между тестами).
        if (isset(static::$taxonomyIdCache[$slug])
            && ! Taxonomy::where('id', static::$taxonomyIdCache[$slug])->exists()) {
            unset(static::$taxonomyIdCache[$slug]);
        }

        if (! isset(static::$taxonomyIdCache[$slug])) {
            $taxonomy = Taxonomy::firstOrCreate(['slug' => $slug], ['name' => static::taxonomyName()]);
            static::$taxonomyIdCache[$slug] = $taxonomy->id;
        }

        return static::$taxonomyIdCache[$slug];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\CategoryFactory::new();
    }

    /**
     * Get parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Get child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('priority');
    }

    /**
     * Get products in this category.
     */
    public function products(): BelongsToMany
    {
        // Pivot заполняется с Product->taxons() как model_type = Product::class; morphToMany с Category подставляет Category::class.
        return $this->belongsToMany(
            Product::class,
            'model_taxons',
            'taxon_id',
            'model_id'
        )->where('model_taxons.model_type', Product::class);
    }

    /**
     * Get feature blocks for this category.
     */
    public function featureBlocks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductFeatureBlock::class,
            'category_feature_blocks',
            'category_id',
            'feature_block_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Get delivery blocks for this category.
     */
    public function deliveryBlocks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductDeliveryBlock::class,
            'category_delivery_blocks',
            'category_id',
            'delivery_block_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Get variation attributes for this category (default attributes for products in this category).
     */
    public function variationAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'category_variation_attributes',
            'category_id',
            'attribute_id'
        )->withTimestamps();
    }

    /**
     * Scope a query to only include root categories.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get root path constant.
     */
    public static function getRootPath(): string
    {
        return static::ROOT_PATH;
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
     * Get products count in category (including children).
     */
    public function getProductsCountAttribute(): int
    {
        $count = $this->products()
            ->where('state', Product::ACTIVE)
            ->whereNull('parent_product_id')
            ->count();

        $children = $this->children;
        foreach ($children as $child) {
            $count += $child->products_count;
        }

        return $count;
    }

    /**
     * Get slug for URL.
     */
    public function getSlugAttribute(): string
    {
        $slug = $this->attributes['slug'] ?? null;
        $name = $this->attributes['name'] ?? '';

        return $slug ?? \Str::slug($name);
    }

    /**
     * Check if category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all descendant category IDs (including self and all nested children).
     */
    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        $children = $this->children()->get();
        foreach ($children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * Get all descendant category IDs (including self) - static helper method.
     */
    public static function getAllDescendantIdsFor($categoryId): array
    {
        $categoryId = (int) $categoryId;
        $category = static::find($categoryId);
        if (!$category) {
            return [$categoryId];
        }

        return $category->getAllDescendantIds();
    }

    /**
     * Get full URL path for the category.
     */
    public function getFullPathAttribute(): ?string
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, $category->slug);

            // Загружаем parent если он не загружен и есть parent_id
            if (!$category->relationLoaded('parent') && $category->parent_id) {
                $category->load('parent');
            }

            $category = $category->parent;
        }

        if (empty($path)) {
            return null;
        }

        return static::ROOT_PATH . '/' . implode('/', $path);
    }

    /**
     * Get full path (breadcrumb) as array.
     */
    public function getFullPathArrayAttribute(): array
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
            $category = $category->parent;
        }

        return $path;
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        // Коллекция 'images' для обратной совместимости (если изображение было загружено в старую коллекцию)
        $this->addMediaCollection('images')
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
            ->performOnCollections('image', 'images');

        // Конверсия для основного изображения (Full HD)
        $this->addMediaConversion('fullhd')
            ->width(1920)
            ->height(1080)
            ->fit(Fit::Contain, 1920, 1080)
            ->optimize()
            ->performOnCollections('image', 'images');

        // Конверсия для среднего размера (HD)
        $this->addMediaConversion('hd')
            ->width(1280)
            ->height(720)
            ->fit(Fit::Contain, 1280, 720)
            ->optimize()
            ->performOnCollections('image', 'images');
    }
}
