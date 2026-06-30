/**
 * Управление кэшем SWR: ревалидация при изменении данных на API.
 *
 * Использование:
 * - revalidateCache() — обновить все ключи SWR в фоне
 * - revalidateCache('/api/sliders') — обновить только слайдеры
 * - revalidateCache(CACHE_KEYS.home) — обновить все данные главной
 *
 * Можно вызывать из любого места (не только из React), например:
 * - по событию (админка сохранила слайдер → postMessage / BroadcastChannel)
 * - по таймеру (периодическое обновление задаётся в swrConfig.revalidateInterval)
 * - по фокусу вкладки (уже включено в swrConfig.revalidateOnFocus)
 */

import { mutate } from 'swr';
import { getHomeProductsKey } from '../utils/ssr-to-swr';

/** Ключи кэша SWR, совпадающие с ключами в useSWR и ssr-to-swr */
export const CACHE_KEYS = {
  sliders: '/api/sliders',
  categories: '/api/categories',
  featuredProducts: getHomeProductsKey('featured'),
  newProducts: getHomeProductsKey('new'),
  saleProducts: getHomeProductsKey('sale'),
  interiorIdeas: '/api/interior-ideas',
  sets: '/api/sets',
} as const;

/** Все ключи данных главной страницы (для массовой ревалидации) */
export const HOME_CACHE_KEYS: string[] = [
  CACHE_KEYS.sliders,
  CACHE_KEYS.categories,
  CACHE_KEYS.featuredProducts,
  CACHE_KEYS.newProducts,
  CACHE_KEYS.saleProducts,
  CACHE_KEYS.interiorIdeas,
];

/**
 * Ревалидирует кэш SWR: запрашивает свежие данные с API в фоне и обновляет UI.
 *
 * @param key — один ключ (например '/api/sliders') или массив ключей. Без аргумента — ревалидирует все ключи.
 */
export function revalidateCache(key?: string | string[]): Promise<void> {
  if (key === undefined) {
    return mutate(() => true);
  }
  if (Array.isArray(key)) {
    return Promise.all(key.map((k) => mutate(k))).then(() => {});
  }
  return mutate(key);
}

/**
 * Обновить все данные главной страницы в фоне (слайдеры, категории, товары, идеи интерьера).
 */
export function revalidateHome(): Promise<void> {
  return revalidateCache(HOME_CACHE_KEYS);
}

/**
 * Обновить данные главной страницы для конкретного региона.
 */
export function revalidateHomeForRegion(regionId?: number): Promise<void> {
  return revalidateCache([
    CACHE_KEYS.sliders,
    CACHE_KEYS.categories,
    getHomeProductsKey('featured', regionId),
    getHomeProductsKey('new', regionId),
    getHomeProductsKey('sale', regionId),
    CACHE_KEYS.interiorIdeas,
  ]);
}

const REVALIDATE_EVENT = 'swr-revalidate';

/**
 * Подписаться на событие ревалидации из другого контекста (другая вкладка, админка, BroadcastChannel).
 * Вызовите из корня приложения один раз.
 *
 * @example
 * // В App.tsx или entry-client:
 * useEffect(() => subscribeToRevalidate((keys) => revalidateCache(keys)), []);
 *
 * // Где-то при изменении данных (например, после сохранения в админке):
 * window.dispatchEvent(new CustomEvent(REVALIDATE_EVENT, { detail: ['/api/sliders'] }));
 * // или обновить всё: detail: undefined
 */
export function subscribeToRevalidate(
  handler: (keys?: string[]) => void
): () => void {
  if (typeof window === 'undefined') return () => {};

  const listener = (e: Event) => {
    const detail = (e as CustomEvent<string[] | undefined>).detail;
    handler(detail);
  };

  window.addEventListener(REVALIDATE_EVENT, listener);
  return () => window.removeEventListener(REVALIDATE_EVENT, listener);
}

/** Имя события для ручной ревалидации (для dispatch извне) */
export { REVALIDATE_EVENT };
