<?php

namespace App\Models\Product;

use App\Models\Product\Category;
use App\Models\Traits\Cacheable;
use Database\Factories\ProductDeliveryBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class ProductDeliveryBlock extends Model implements HasMedia
{
    use Cacheable, HasFactory, InteractsWithMedia;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductDeliveryBlockFactory::new();
    }

    protected $fillable = [
        'title',
        'description',
        'icon',
        'icon_color',
        'bg_color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::bootCacheable();
        
        // Очищаем кеш блоков при сохранении/удалении
        static::saved(function ($block) {
            static::clearBlocksCache();
        });
        
        static::deleted(function ($block) {
            static::clearBlocksCache();
        });
    }
    
    /**
     * Очистить кеш всех блоков товаров
     */
    protected static function clearBlocksCache(): void
    {
        try {
            // Очищаем кеш для всех товаров (используем паттерн, если Redis доступен)
            if (config('cache.default') === 'redis' && \Cache::getStore()->getRedis()) {
                $redis = \Cache::getStore()->getRedis();
                $prefix = config('cache.prefix', '');
                
                $keys = $redis->keys($prefix . '*delivery_blocks_product_*');
                foreach ($keys as $key) {
                    $redis->del($key);
                }
                
                $keys = $redis->keys($prefix . '*category_with_delivery_blocks_*');
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            } else {
                // Fallback: очищаем кеш через Cache facade (работает для всех драйверов)
                // Кеш будет очищен автоматически при следующем запросе через TTL
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки кеша
        }
    }

    /**
     * Get categories that have this delivery block.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_delivery_blocks',
            'delivery_block_id',
            'category_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Get products that have this delivery block (for override).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_delivery_blocks_pivot',
            'delivery_block_id',
            'product_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * Scope a query to only include active blocks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get delivery blocks for a product from its category.
     * 
     * Logic:
     * 1. Get blocks from product's category
     * 2. If category has no blocks, return empty collection
     */
    public static function getForProduct(Product $product): Collection
    {
        $cacheKey = "delivery_blocks_product_{$product->id}";
        
        return static::cached($cacheKey, function () use ($product) {
            // Product-level override has priority over category blocks
            $productBlocks = $product->deliveryBlocks()
                ->active()
                ->orderBy('product_delivery_blocks_pivot.sort_order')
                ->orderBy('id')
                ->get();
            if ($productBlocks->isNotEmpty()) {
                return $productBlocks;
            }

            // Get blocks from product's category
            // Load taxons if not loaded
            if (!$product->relationLoaded('taxons')) {
                $product->load('taxons');
            }
            
            // Get first category ID from taxons
            $taxon = $product->taxons->first();
            if ($taxon) {
                // Load Category model by ID (taxon_id is the same as category id)
                // Используем кеш для категории с блоками
                $categoryCacheKey = "category_with_delivery_blocks_{$taxon->id}";
                $category = Category::cached($categoryCacheKey, function () use ($taxon) {
                    return Category::with(['deliveryBlocks' => function ($query) {
                        $query->where('is_active', true)
                            ->orderBy('category_delivery_blocks.sort_order')
                            ->orderBy('id');
                    }])->find($taxon->id);
                }, 36000);
                
                if ($category && $category->relationLoaded('deliveryBlocks')) {
                    $categoryBlocks = $category->deliveryBlocks->values();
                    if ($categoryBlocks->isNotEmpty()) {
                        return $categoryBlocks;
                    }
                } elseif ($category) {
                    // Fallback: query if not eager loaded
                    $categoryBlocks = $category->deliveryBlocks()->active()
                        ->orderBy('category_delivery_blocks.sort_order')
                        ->orderBy('id')
                        ->get();
                    if ($categoryBlocks->isNotEmpty()) {
                        return $categoryBlocks;
                    }
                }
            }
            
            return collect();
        }, 36000);
    }

    /**
     * Get delivery blocks for a category.
     */
    public static function getForCategory(Category $category): Collection
    {
        $cacheKey = "delivery_blocks_category_{$category->id}";
        
        return static::cached($cacheKey, function () use ($category) {
            return $category->deliveryBlocks()->active()->ordered()->get();
        }, 36000);
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
            ->singleFile();
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Конверсия для миниатюр
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->fit(Fit::Contain, 100, 100)
            ->optimize()
            ->performOnCollections('icon');
    }

    /**
     * Get icon image URL.
     */
    public function getIconImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('icon');
    }

    /**
     * Get icon image thumbnail URL.
     */
    public function getIconImageThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('icon', 'thumb');
    }
}
