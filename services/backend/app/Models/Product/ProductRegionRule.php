<?php

namespace App\Models\Product;

use App\Models\Shipping\ShippingLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Правила работы с корзиной для товаров по регионам
 * 
 * Позволяет:
 * - Переопределять цены товаров в зависимости от региона
 * - Модифицировать цены (фиксированная сумма, процент, множитель)
 * - Управлять видимостью товаров в регионах
 * - Переопределять сроки доставки
 */
class ProductRegionRule extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (self $rule): void {
            $rule->flushRelatedCatalogCache('saved');
        });

        static::deleted(function (self $rule): void {
            $rule->flushRelatedCatalogCache('deleted');
        });
    }

    protected $fillable = [
        'product_id',
        'variant_id',
        'shipping_location_id',
        'price_override',
        'price_modifier_type',
        'price_modifier_value',
        'is_hidden',
        'delivery_days_override',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'price_modifier_value' => 'decimal:2',
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
            'delivery_days_override' => 'integer',
            'priority' => 'integer',
        ];
    }

    /**
     * Получить товар
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Получить вариацию
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'variant_id');
    }

    /**
     * Получить локацию доставки
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    /**
     * Получить локацию доставки (алиас для ясности)
     */
    public function shippingLocation(): BelongsTo
    {
        return $this->belongsTo(ShippingLocation::class, 'shipping_location_id');
    }

    /**
     * Scope для получения только активных правил
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для получения правил по локации доставки
     */
    public function scopeForRegion($query, $locationId)
    {
        return $query->where('shipping_location_id', $locationId);
    }

    /**
     * Scope для получения правил по локации доставки и всех её родителей (наследование)
     */
    public function scopeForLocationWithInheritance($query, ShippingLocation $location)
    {
        $locationIds = $location->getAncestorsIds();
        return $query->whereIn('shipping_location_id', $locationIds);
    }

    /**
     * Scope для получения правил по товару
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope для получения правил по вариации
     */
    public function scopeForVariant($query, $variantId)
    {
        return $query->where('variant_id', $variantId);
    }

    /**
     * Применить правило к цене
     * 
     * @param float $basePrice Базовая цена товара
     * @return float Цена с учетом правила
     */
    public function applyToPrice(float $basePrice): float
    {
        // Если есть переопределение цены, используем его
        if ($this->price_override !== null) {
            return (float) $this->price_override;
        }

        // Если есть модификатор, применяем его
        if ($this->price_modifier_type && $this->price_modifier_value !== null) {
            return match ($this->price_modifier_type) {
                'fixed' => $basePrice + (float) $this->price_modifier_value,
                'percent' => $basePrice * (1 + (float) $this->price_modifier_value / 100),
                'multiply' => $basePrice * (float) $this->price_modifier_value,
                default => $basePrice,
            };
        }

        // Если правила нет, возвращаем базовую цену
        return $basePrice;
    }

    /**
     * Инвалидация кэша каталога и карточки товара при изменении региональных правил.
     */
    private function flushRelatedCatalogCache(string $event): void
    {
        $tags = ['product_index_cache', 'product_search_cache'];

        try {
            if (in_array(config('cache.default'), ['redis', 'memcached'], true)) {
                Cache::tags($tags)->flush();
                Log::info('Flushed tagged catalog cache for product region rule change', [
                    'event' => $event,
                    'rule_id' => $this->id,
                    'product_id' => $this->product_id,
                    'variant_id' => $this->variant_id,
                    'shipping_location_id' => $this->shipping_location_id,
                    'cache_tags' => $tags,
                ]);
            } else {
                Cache::forget(Product::cacheKey('index'));
                Log::warning('Cache tags are not supported, fallback invalidation used for product region rule', [
                    'event' => $event,
                    'rule_id' => $this->id,
                    'cache_driver' => config('cache.default'),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to flush regional rule cache tags', [
                'event' => $event,
                'rule_id' => $this->id,
                'cache_tags' => $tags,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
