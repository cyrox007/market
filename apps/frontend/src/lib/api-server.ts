/**
 * Server-side API fetcher with ISR (Incremental Static Regeneration) support
 *
 * This module provides server-side data fetching with caching and revalidation.
 * For React + Vite, this can be used with SSR frameworks or as a client-side cache.
 */

import { api } from './api';
import type { Slider, Category, Product } from './api';

export type { Slider, Category, Product };

// Cache configuration (временно 3 сек для быстрого отображения изменений товаров из админки; вернуть 120 для продакшена)
const CACHE_REVALIDATE_TIME = 3;
const CACHE_STORAGE = new Map<string, { data: any; timestamp: number }>();

interface CacheOptions {
  revalidate?: number; // seconds
  tags?: string[]; // for cache invalidation
}

/**
 * Server-side fetch with caching
 */
export async function fetchWithCache<T>(
  key: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {},
): Promise<T> {
  const revalidate = options.revalidate ?? CACHE_REVALIDATE_TIME;
  const cached = CACHE_STORAGE.get(key);

  // Check if cache is valid
  if (cached) {
    const age = (Date.now() - cached.timestamp) / 1000;
    if (age < revalidate) {
      return cached.data as T;
    }
  }

  // Fetch fresh data
  try {
    const data = await fetcher();
    CACHE_STORAGE.set(key, {
      data,
      timestamp: Date.now(),
    });
    return data;
  } catch (error) {
    if (cached) {
      return cached.data as T;
    }
    throw error;
  }
}

/**
 * Invalidate cache by tag
 */
export function invalidateCache(tag: string): void {
  // For now, clear all cache. Can be enhanced with tag-based invalidation
  CACHE_STORAGE.clear();
}

/**
 * Get sliders with caching
 */
export async function getSliders(options?: CacheOptions): Promise<Slider[]> {
  const data = await fetchWithCache('sliders:list', () => api.sliders.list(), options);
  return data.data;
}

/**
 * Get single slider with caching
 */
export async function getSlider(slug: string, options?: CacheOptions): Promise<Slider> {
  const data = await fetchWithCache(`sliders:${slug}`, () => api.sliders.get(slug), options);
  return data.slider;
}

/**
 * Get categories with caching
 */
export async function getCategories(options?: CacheOptions): Promise<Category[]> {
  const data = await fetchWithCache('categories:list', () => api.categories.list(), options);
  return data.data;
}

/**
 * Get category tree with caching
 */
export async function getCategoryTree(options?: CacheOptions): Promise<Category[]> {
  const data = await fetchWithCache('categories:tree', () => api.categories.tree(), options);
  return data.tree;
}

/**
 * Get featured products with caching
 */
export async function getFeaturedProducts(options?: CacheOptions): Promise<Product[]> {
  const data = await fetchWithCache('products:featured', () => api.products.featured(), options);
  return data.data;
}

/**
 * Get new products with caching
 */
export async function getNewProducts(options?: CacheOptions): Promise<Product[]> {
  const data = await fetchWithCache('products:new', () => api.products.new(), options);
  return data.data;
}

/**
 * Get sale products with caching
 */
export async function getSaleProducts(options?: CacheOptions): Promise<Product[]> {
  const data = await fetchWithCache('products:sale', () => api.products.sale(), options);
  return data.data;
}
