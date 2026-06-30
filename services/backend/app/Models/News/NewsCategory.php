<?php

namespace App\Models\News;

use App\Contracts\Models\PageableContract;
use App\Models\Traits\Cacheable;
use App\Models\Traits\Pageable;
use App\Models\Traits\SEO\MetaUniversalSEO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RalphJSmit\Laravel\SEO\Support\HasSEO;

class NewsCategory extends Model implements PageableContract
{
    use Cacheable, HasFactory, Pageable, HasSEO, MetaUniversalSEO;

    protected $table = 'news_categories';

    public const ROOT_PATH = '/news/category';

    public const ACTIVE = true;

    protected $fillable = [
        'title',
        'slug',
        'parent_id',
        'description',
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
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\NewsCategoryFactory::new();
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
     * Get articles in this category.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'category_id');
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

        return self::ROOT_PATH . '/' . implode('/', $path);
    }

    /**
     * Check if category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
}


