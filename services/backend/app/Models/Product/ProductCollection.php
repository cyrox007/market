<?php

namespace App\Models\Product;

use App\Services\Catalog\ProductCollectionCacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class ProductCollection extends Model
{
    use HasFactory;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (ProductCollection $collection) {
            $collection->flushHomeCaches();
        });

        static::deleted(function (ProductCollection $collection) {
            $collection->flushHomeCaches();
        });
    }

    /**
     * Сброс кэша API главной и SSR (после правок в админке или sync товаров).
     */
    public function flushHomeCaches(): void
    {
        app(ProductCollectionCacheService::class)->flushForCollection($this);
    }

    protected $fillable = [
        'name',
        'slug',
        'scope_type',
        'is_auto',
        'is_active',
        'recommend_collection_members',
        'priority',
        'limit',
    ];

    protected $casts = [
        'is_auto' => 'boolean',
        'is_active' => 'boolean',
        'recommend_collection_members' => 'boolean',
        'priority' => 'integer',
        'limit' => 'integer',
    ];

    /**
     * Получить товары в подборке
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_collection')
            ->withTimestamps()
            ->withPivot('sort_order');
    }

    /**
     * Scope для активных подборок
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для подборок по типу скоупа
     */
    public function scopeByScopeType(Builder $query, string $scopeType): Builder
    {
        return $query->where('scope_type', $scopeType);
    }

    /**
     * Scope для подборок по slug
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Синхронизировать товары в автоматической подборке по скоупу
     */
    public function syncProductsByScope(): void
    {
        if (!$this->is_auto || !$this->scope_type) {
            return;
        }

        $query = Product::query()->catalogItems();

        // Применяем соответствующий скоуп
        switch ($this->scope_type) {
            case 'featured':
                $query->featured();
                break;
            case 'new':
                $query->new();
                break;
            case 'sale':
                $query->sale();
                break;
        }

        // Получаем товары с ограничением
        $products = $query->limit($this->limit)->get();

        // Синхронизируем товары с сохранением порядка
        $syncData = [];
        foreach ($products as $index => $product) {
            $syncData[$product->id] = ['sort_order' => $index];
        }

        $this->products()->sync($syncData);
    }

    /**
     * Получить товары для API с учетом типа подборки
     */
    public function getProductsForApi()
    {
        if ($this->is_auto && $this->scope_type) {
            // Для автоматических подборок используем скоуп напрямую
            $query = Product::query()
                ->with([
                    'taxons',
                    'variants' => fn ($q) => $q->active()->orderBy('id'),
                    'attributeValues.attribute',
                    'manufacturer',
                    'media',
                ])
                ->catalogItems();

            switch ($this->scope_type) {
                case 'featured':
                    $query->featured();
                    break;
                case 'new':
                    $query->new();
                    break;
                case 'sale':
                    $query->sale();
                    break;
            }

            return $query->limit($this->limit)->get();
        } else {
            // Existing selected groups expand to their individual active SKUs.
            // Explicitly selected SKU IDs remain exact; no grouping in the API.
            $ids = $this->products()->orderBy('product_product_collection.sort_order')->pluck('products.id')->all();
            if ($ids === []) {
                return collect();
            }
            $query = Product::query()->catalogItems()
                ->where(fn ($scope) => $scope->whereIn('products.id', $ids)->orWhereIn('products.parent_product_id', $ids))
                ->with([
                    'taxons',
                    'variants' => fn ($q) => $q->active()->orderBy('id'),
                    'attributeValues.attribute',
                    'manufacturer',
                    'media',
                ]);
            $cases = [];
            $bindings = [];
            foreach ($ids as $rank => $id) {
                $cases[] = 'WHEN products.id = ? OR products.parent_product_id = ? THEN ?';
                array_push($bindings, $id, $id, $rank);
            }

            return $query->orderByRaw('CASE '.implode(' ', $cases).' ELSE '.count($ids).' END', $bindings)
                ->orderBy('products.id')->limit($this->limit)->get();
        }
    }
}
