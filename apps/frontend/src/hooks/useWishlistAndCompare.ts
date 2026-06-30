/**
 * Хук для загрузки wishlist и compare с использованием SWR
 * Централизует загрузку этих данных и предотвращает дублирование запросов
 */

import { useMemo } from 'react';
import useSWR from 'swr';
import { api } from '../lib/api';
import type { Product } from '../lib/api';

interface WishlistItem {
  product_id?: number;
  product?: Product;
}

interface WishlistAndCompareData {
  wishlistProductIds: number[];
  compareProductIds: number[];
}

/**
 * Хук для получения списков wishlist и compare
 */
export function useWishlistAndCompare() {
  // Загружаем wishlist
  const { data: wishlistData, mutate: mutateWishlist } = useSWR(
    '/api/wishlist',
    () => api.wishlist.list().catch(() => ({ data: [] })),
    {
      revalidateOnFocus: false, // Не обновляем при фокусе
      revalidateIfStale: false, // Не обновляем устаревшие данные автоматически
      dedupingInterval: 5000, // Дедупликация 5 секунд
    }
  );

  // Загружаем compare
  const { data: compareData, mutate: mutateCompare } = useSWR(
    '/api/compare',
    () => api.compare.list().catch(() => ({ products: [] })),
    {
      revalidateOnFocus: false,
      revalidateIfStale: false,
      dedupingInterval: 5000,
    }
  );

  // Вычисляем списки ID товаров
  const data = useMemo<WishlistAndCompareData>(() => {
    const wishlistItems: WishlistItem[] = wishlistData?.data || [];
    const compareProducts: Product[] = compareData?.products || [];

    const wishlistProductIds = wishlistItems.map(
      (item) => item.product_id || item.product?.id
    ).filter((id): id is number => id !== undefined);

    const compareProductIds = compareProducts.map((p) => p.id);

    return {
      wishlistProductIds,
      compareProductIds,
    };
  }, [wishlistData, compareData]);

  return {
    ...data,
    isLoading: !wishlistData && !compareData,
    mutateWishlist,
    mutateCompare,
  };
}
