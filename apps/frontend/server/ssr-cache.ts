/**
 * SSR Cache - in-memory кеш для SSR запросов
 * Ускоряет повторные запросы с теми же параметрами
 */

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
}

interface CacheOptions {
  ttl?: number; // Time to live в секундах (по умолчанию 120)
  key?: string; // Кастомный ключ кеша
}

class SSRCache {
  private cache = new Map<string, CacheEntry<any>>();
  private defaultTTL = 120; // 2 минуты по умолчанию

  /**
   * Получить данные из кеша (только свежие, не истёкшие).
   * При истечении записи не удаляем — остаётся для getStale() при ошибке API.
   */
  get<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) return null;

    if (Date.now() > entry.expiresAt) {
      return null;
    }

    return entry.data as T;
  }

  /**
   * Получить данные из кеша даже если TTL истёк (для fallback при ошибке API).
   * Чтобы кэш отдавал последние данные из API, а не статичный fallback.
   */
  getStale<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) return null;
    return entry.data as T;
  }

  /**
   * Сохранить данные в кеш
   */
  set<T>(key: string, data: T, options?: CacheOptions): void {
    const ttl = (options?.ttl || this.defaultTTL) * 1000; // Конвертируем в миллисекунды
    const expiresAt = Date.now() + ttl;

    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      expiresAt,
    });
  }

  /**
   * Проверить, есть ли данные в кеше (без получения)
   */
  has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) return false;

    if (Date.now() > entry.expiresAt) {
      this.cache.delete(key);
      return false;
    }

    return true;
  }

  /**
   * Удалить данные из кеша
   */
  delete(key: string): void {
    this.cache.delete(key);
  }

  /**
   * Очистить весь кеш
   */
  clear(): void {
    this.cache.clear();
  }

  /**
   * Удалить записи, ключ которых начинается с одного из префиксов (для инвалидации с бэкенда).
   */
  deleteByPrefixes(prefixes: string[]): number {
    let removed = 0;
    for (const key of Array.from(this.cache.keys())) {
      if (prefixes.some((prefix) => key.startsWith(prefix))) {
        this.cache.delete(key);
        removed++;
      }
    }
    return removed;
  }

  /**
   * Очистить устаревшие записи
   */
  cleanup(): void {
    const now = Date.now();
    for (const [key, entry] of this.cache.entries()) {
      if (now > entry.expiresAt) {
        this.cache.delete(key);
      }
    }
  }

  /**
   * Получить статистику кеша
   */
  getStats() {
    return {
      size: this.cache.size,
      keys: Array.from(this.cache.keys()),
    };
  }
}

// Глобальный экземпляр кеша
export const ssrCache = new SSRCache();

// Периодическая очистка устаревших записей (каждые 5 минут)
if (typeof setInterval !== 'undefined') {
  setInterval(
    () => {
      ssrCache.cleanup();
    },
    5 * 60 * 1000,
  );
}

/**
 * Ключ кеша для API запроса.
 * Строится только из endpoint + params — без тегов и без userId для каталога/товаров/категорий,
 * чтобы один и тот же кеш подходил всем «похожим» запросам (одинаковые категория, регион, фильтры).
 * userId передаём только для персонализированных данных (auth, cart, wishlist, compare).
 */
export function createCacheKey(
  endpoint: string,
  params?: Record<string, any>,
  userId?: number,
): string {
  const parts = [endpoint];

  if (params) {
    const sortedParams = Object.keys(params)
      .sort()
      .map((key) => `${key}=${JSON.stringify(params[key])}`)
      .join('&');
    if (sortedParams) {
      parts.push(sortedParams);
    }
  }

  if (userId != null) {
    parts.push(`user=${userId}`);
  }

  return parts.join('?');
}

/**
 * Обертка для кеширования асинхронных функций
 */
export async function withCache<T>(
  key: string,
  fetcher: () => Promise<T>,
  options?: CacheOptions,
): Promise<T> {
  // Проверяем кеш
  const cached = ssrCache.get<T>(key);
  if (cached !== null) {
    return cached;
  }

  // Выполняем запрос
  try {
    const data = await fetcher();
    ssrCache.set(key, data, options);
    return data;
  } catch (error) {
    // В случае ошибки возвращаем последние данные из API (даже устаревшие), а не пустой результат
    const staleCache = ssrCache.getStale<T>(key);
    if (staleCache !== null) {
      console.warn(`[SSR Cache] Using stale cache for ${key} due to error:`, error);
      return staleCache;
    }
    throw error;
  }
}

/** Ключи, по которым уже идёт фоновое обновление (чтобы не дергать API пачкой запросов). */
const revalidating = new Set<string>();

/**
 * Stale-while-revalidate: следующий пользователь сразу получает кэш, в фоне кэш обновляется.
 * — Есть свежий кэш → возвращаем его.
 * — Есть устаревший кэш → возвращаем его сразу, в фоне запускаем fetcher и обновляем кэш.
 * — Нет кэша → ждём fetcher, кладём в кэш, возвращаем.
 */
export async function withCacheSWR<T>(
  key: string,
  fetcher: () => Promise<T>,
  options?: CacheOptions,
): Promise<T> {
  const stale = ssrCache.getStale<T>(key);
  const fresh = ssrCache.get<T>(key);

  if (fresh !== null) {
    return fresh;
  }

  if (stale !== null) {
    if (!revalidating.has(key)) {
      revalidating.add(key);
      fetcher()
        .then((data) => {
          ssrCache.set(key, data, options);
        })
        .catch((err) => {
          console.warn(`[SSR Cache] Background revalidate failed for ${key}:`, err);
        })
        .finally(() => {
          revalidating.delete(key);
        });
    }
    return stale;
  }

  try {
    const data = await fetcher();
    ssrCache.set(key, data, options);
    return data;
  } catch (error) {
    throw error;
  }
}
