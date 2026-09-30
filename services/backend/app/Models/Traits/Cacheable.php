<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Универсальный трейт для кэширования
 *
 * Особенности:
 * - Автоматическое использование тегов кэша (если поддерживается)
 * - Точечная инвалидация только связанных кэшей
 * - Опциональная асинхронная инвалидация
 * - Все работает "из коробки" без дополнительной настройки
 */
trait Cacheable
{
    protected static function bootCacheable()
    {
        static::saved(fn($model) => $model->flushCache());
        static::deleted(fn($model) => $model->flushCache());
    }

    /**
     * Получить ключ кэша
     */
    public static function cacheKey(string $suffix = 'index'): string
    {
        return strtolower(class_basename(static::class)) . '_' . $suffix;
    }

    /**
     * Получить теги кэша для модели
     */
    public static function getCacheTags(): array
    {
        $modelName = strtolower(class_basename(static::class));
        return ["{$modelName}_cache", "global_cache"];
    }

    /**
     * Кэшировать данные
     * Автоматически использует теги, если драйвер поддерживает
     */
    public static function cached(string $suffix = 'index', ?callable $callback = null, int $ttl = 36000)
    {
        $key = static::cacheKey($suffix);

        // Автоматически используем теги, если поддерживается
        if (static::supportsCacheTags()) {
            $tags = static::getCacheTags();
            return Cache::tags($tags)->remember($key, $ttl, $callback ?? fn() => static::all());
        }

        // Fallback для драйверов без поддержки тегов
        return Cache::remember($key, $ttl, $callback ?? fn() => static::all());
    }

    /**
     * Кэшировать с дополнительными тегами (например, для категории)
     */
    public static function cachedWithTags(array $additionalTags, string $suffix = 'index', ?callable $callback = null, int $ttl = 36000)
    {
        $key = static::cacheKey($suffix);

        if (static::supportsCacheTags()) {
            $tags = array_merge(static::getCacheTags(), $additionalTags);
            return Cache::tags($tags)->remember($key, $ttl, $callback ?? fn() => static::all());
        }

        return Cache::remember($key, $ttl, $callback ?? fn() => static::all());
    }

    /**
     * Проверить, поддерживает ли драйвер кэша теги
     */
    protected static function supportsCacheTags(): bool
    {
        $driver = config('cache.default');
        return in_array($driver, ['redis', 'memcached']);
    }

    /**
     * Сбросить кэш модели
     * Автоматически определяет оптимальный способ инвалидации
     */
    public function flushCache(): void
    {
        // Опциональная асинхронная инвалидация для больших нагрузок
        if (config('cache.async_invalidation', false)) {
            dispatch(function () {
                $this->performCacheFlush();
            })->afterResponse();
            return;
        }

        $this->performCacheFlush();
    }

    /**
     * Выполнить сброс кэша
     */
    protected function performCacheFlush(): void
    {
        $modelName = strtolower(class_basename(static::class));

        // Сбрасываем прямые кэши модели
        $this->flushDirectCache();

        // Специфичная логика для разных моделей
        if ($modelName === 'product') {
            $this->flushProductCache();
        } elseif ($modelName === 'category') {
            $this->flushCategoryCache();
        } else {
            // Для остальных моделей сбрасываем базовые ключи
            $this->flushBasicCache();
        }
    }

    /**
     * Сбросить прямые кэши модели (show, related)
     */
    protected function flushDirectCache(): void
    {
        $id = $this->getKey();

        // Сбрасываем кэш конкретной записи
        Cache::forget(static::cacheKey('show_' . $id));

        if (isset($this->slug)) {
            Cache::forget(static::cacheKey('show_' . $this->slug));
        }

        if (method_exists($this, 'getSlug')) {
            Cache::forget(static::cacheKey('show_' . $this->getSlug()));
        }

        // Сбрасываем связанные кэши
        Cache::forget(static::cacheKey("related_{$id}"));
        Cache::forget(static::cacheKey("bundle_{$id}"));
    }

    /**
     * Сбросить базовые кэши (для моделей без специфичной логики)
     */
    protected function flushBasicCache(): void
    {
        Cache::forget(static::cacheKey('index'));
        Cache::forget(static::cacheKey('tree'));
        Cache::forget(static::cacheKey('featured'));
        Cache::forget(static::cacheKey('new'));
        Cache::forget(static::cacheKey('sale'));

        // Если поддерживаются теги, сбрасываем через теги
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(static::getCacheTags())->flush();
            } catch (\Exception $e) {
                // Fallback уже выполнен выше
            }
        }
    }

    /**
     * Оптимизированный сброс кэша для Product
     */
    protected function flushProductCache(): void
    {
        $productId = $this->getKey();

        // Сбрасываем ВСЕ ключи страницы товара (show_{slug}, show_{slug}_region_X и т.д.),
        // иначе после добавления/изменения вариаций фронт продолжит получать старый кэш
        $this->flushProductShowCacheByPattern();

        // Получаем категории товара для точечной инвалидации
        $categoryIds = $this->getCategoryIds();

        // Сбрасываем кэши только тех категорий, где находится товар
        foreach ($categoryIds as $categoryId) {
            $this->flushCategoryIndexCache($categoryId);
        }

        // Сбрасываем кэши родительского товара, если это вариация
        if ($this->isVariant() && isset($this->parent_product_id)) {
            $parent = static::find($this->parent_product_id);
            if ($parent) {
                $parent->flushDirectCache();
                $parent->flushProductShowCacheByPattern();
            }
        }

        // Сбрасываем кэши вариаций, если это родительский товар
        if (!$this->isVariant()) {
            $this->flushVariantsCache();
        }

        // Сбрасываем кэши списков (featured, new, sale)
        Cache::forget(static::cacheKey('featured'));
        Cache::forget(static::cacheKey('new'));
        Cache::forget(static::cacheKey('sale'));

        // Сбрасываем кэши поиска и индекса через теги (быстрее чем SCAN)
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(['product_index_cache', 'product_search_cache'])->flush();
            } catch (\Exception $e) {
                // Fallback: сбрасываем базовые ключи
                $this->flushProductIndexCacheFallback();
            }
        } else {
            // Fallback для драйверов без тегов
            $this->flushProductIndexCacheFallback();
        }

        // Сбрасываем кэши категорий, в которых находится товар
        $this->flushCategoryCaches();
    }

    /**
     * Оптимизированный сброс кэша для Category
     */
    protected function flushCategoryCache(): void
    {
        $categoryId = $this->getKey();

        // Ключи show/tree/index кладутся через cached() с тегами модели: на redis они
        // живут в теговом пространстве, поэтому один plain-forget их не видит — гасим и там.
        $keys = [
            static::cacheKey('show_' . $categoryId),
            static::cacheKey('tree'),
            static::cacheKey('index'),
        ];
        if (isset($this->slug)) {
            $keys[] = static::cacheKey('show_' . $this->slug);
        }
        foreach ($keys as $key) {
            Cache::forget($key);
            if (static::supportsCacheTags()) {
                try {
                    Cache::tags(static::getCacheTags())->forget($key);
                } catch (\Throwable $e) {
                    // теговый форгет недоступен — plain-forget уже выполнен
                }
            }
        }

        // Сбрасываем маркер «пустая категория» (при изменении категории список товаров мог измениться)
        Cache::forget('empty_category:' . $categoryId);

        // Сбрасываем кэши товаров этой категории через теги (оптимально)
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(["category_{$categoryId}_products"])->flush();
            } catch (\Exception $e) {
                // Fallback
                $this->flushCategoryProductsCacheFallback();
            }
        } else {
            $this->flushCategoryProductsCacheFallback();
        }
    }

    /**
     * Получить ID категорий товара
     */
    protected function getCategoryIds(): array
    {
        if (method_exists($this, 'taxons')) {
            return $this->taxons()->pluck('id')->toArray();
        }

        return [];
    }

    /**
     * Сбросить кэш индекса категории
     */
    protected function flushCategoryIndexCache(int $categoryId): void
    {
        $categoryModel = \App\Models\Product\Category::class;

        // Сбрасываем только кэш этой категории
        Cache::forget($categoryModel::cacheKey("show_{$categoryId}"));

        // Сбрасываем мета-данные фильтров категории (используются в API списка товаров)
        Cache::forget('product_filters_meta:cat:' . $categoryId);

        // Сбрасываем маркер «пустая категория» (при добавлении товара категория может стать непустой)
        Cache::forget('empty_category:' . $categoryId);

        // Сбрасываем кэши товаров этой категории через теги
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(["category_{$categoryId}_products"])->flush();
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }
    }

    /**
     * Сбросить ВСЕ ключи кэша страницы товара (show_{slug}, show_{slug}_region_X и т.д.).
     * API кэширует ответ с тегом product_show_{id}; при сбросе по этому тегу удаляются все варианты ключа.
     * Без тегов Cache::forget() не удаляет ключи, сохранённые через Cache::tags()->remember().
     */
    protected function flushProductShowCacheByPattern(): void
    {
        $id = $this->getKey();
        $slug = $this->slug ?? (method_exists($this, 'getSlug') ? $this->getSlug() : null);
        if ($id === null && $slug === null) {
            return;
        }

        // При поддержке тегов API кэширует страницу товара с тегом product_show_{id} — сбрасываем по тегу
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(['product_show_' . $id])->flush();
            } catch (\Throwable $e) {
                Log::warning('flushProductShowCacheByPattern (tags) failed: ' . $e->getMessage());
            }
        }

        // Точечный сброс (для драйверов без тегов и на случай кэша без тега)
        Cache::forget(static::cacheKey('show_' . $id));
        if ($slug !== null && $slug !== '') {
            Cache::forget(static::cacheKey('show_' . $slug));
        }
    }

    /**
     * Сбросить кэши вариаций
     */
    protected function flushVariantsCache(): void
    {
        if (method_exists($this, 'variants')) {
            $variants = $this->variants()->pluck('id');
            foreach ($variants as $variantId) {
                Cache::forget(static::cacheKey("show_{$variantId}"));
            }
        }
    }

    /**
     * Сбросить кэши категорий, в которых находится товар
     */
    protected function flushCategoryCaches(): void
    {
        $categoryModel = \App\Models\Product\Category::class;

        // Сбрасываем базовые кэши категорий
        Cache::forget($categoryModel::cacheKey('index'));
        Cache::forget($categoryModel::cacheKey('tree'));
    }

    /**
     * Fallback: сбросить кэши товаров категории (без тегов)
     */
    protected function flushCategoryProductsCacheFallback(): void
    {
        $productModel = \App\Models\Product\Product::class;
        Cache::forget($productModel::cacheKey('index'));
        Cache::forget($productModel::cacheKey('featured'));
        Cache::forget($productModel::cacheKey('new'));
        Cache::forget($productModel::cacheKey('sale'));
    }

    /**
     * Fallback: сбросить кэши индекса товаров через SCAN (медленно, но работает)
     */
    protected function flushProductIndexCacheFallback(): void
    {
        Cache::forget(static::cacheKey('index'));

        // Попытка сбросить ключи с параметрами через Redis SCAN
        // ВНИМАНИЕ: Это может быть медленно при большом количестве ключей!
        if (config('cache.default') === 'redis') {
            try {
                $store = Cache::getStore();
                if (method_exists($store, 'connection')) {
                    $redis = $store->connection();
                    $pattern = static::cacheKey('index') . '*';
                    $cursor = 0;
                    $keys = [];
                    $maxIterations = 10; // Ограничиваем для производительности

                    $iterations = 0;
                    do {
                        $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
                        $cursor = $result[0];
                        $keys = array_merge($keys, $result[1]);
                        $iterations++;
                    } while ($cursor !== 0 && $iterations < $maxIterations);

                    if (!empty($keys)) {
                        // Удаляем ключи батчами для производительности
                        $chunks = array_chunk($keys, 100);
                        foreach ($chunks as $chunk) {
                            $redis->del($chunk);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to flush cache by pattern: ' . $e->getMessage());
            }
        }
    }

    /**
     * Сбросить все кэши модели (для критических операций)
     */
    public function flushAllCache(): void
    {
        if (static::supportsCacheTags()) {
            try {
                Cache::tags(static::getCacheTags())->flush();
            } catch (\Exception $e) {
                $this->flushBasicCache();
            }
        } else {
            $this->flushBasicCache();
        }
    }
}
