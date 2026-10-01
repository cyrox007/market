/**
 * Хук для загрузки wishlist и compare с использованием SWR
 * Централизует загрузку этих данных и предотвращает дублирование запросов
 */

import { useCallback, useMemo } from 'react';
import useSWR from 'swr';
import { api } from '../lib/api';
import type { Product } from '../lib/api';
import { useCounters } from './useCounters';

interface WishlistItem {
  product_id?: number;
  product?: Product;
}

interface WishlistData {
  data: WishlistItem[];
}

interface CompareData {
  products: Product[];
}

interface WishlistAndCompareData {
  wishlistProductIds: number[];
  compareProductIds: number[];
}

/** В избранное всегда кладём родительский товар, не вариант */
export const productIdForWishlist = (product: Product): number => product.id;

/** В сравнение у вариативного товара идёт первый доступный вариант */
export function productIdForCompare(product: Product): number {
  if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
    return product.first_available_variant_id;
  }
  return product.id;
}

const wishlistItemId =(item: WishlistItem) => item.product_id || item.product?.id;

function withWishlist(current: WishlistData | undefined, id: number, on: boolean): WishlistData {
  const items = (current?.data ?? []).filter((item) => wishlistItemId(item) !== id);
  return { ...current, data: on ? [...items, { product_id: id }] : items };
}

/** Для списка id хватает заглушки `{ id }` — страница сравнения грузит товары сама */
function withCompare(current: CompareData | undefined, id: number, on: boolean): CompareData {
  const products = (current?.products ?? []).filter((p) => p.id !== id);
  return { ...current, products: on ? [...products, { id } as Product] : products };
}

/**
 * Хук для получения списков wishlist и compare
 */
export function useWishlistAndCompare() {
  const { bumpWishlistCount, bumpCompareCount, refreshWishlistCount } = useCounters();

  // Загружаем wishlist
  const { data: wishlistData, mutate: mutateWishlist } = useSWR<WishlistData>(
    '/api/wishlist',
    () => api.wishlist.list().catch(() => ({ data: [] })),
    {
      revalidateOnFocus: false, // Не обновляем при фокусе
      revalidateIfStale: false, // Не обновляем устаревшие данные автоматически
      dedupingInterval: 5000, // Дедупликация 5 секунд
    },
  );

  // Загружаем compare
  const { data: compareData, mutate: mutateCompare } = useSWR<CompareData>(
    '/api/compare',
    () => api.compare.list().catch(() => ({ products: [] })),
    {
      revalidateOnFocus: false,
      revalidateIfStale: false,
      dedupingInterval: 5000,
    },
  );

  // Вычисляем списки ID товаров
  const data = useMemo<WishlistAndCompareData>(() => {
    const wishlistProductIds = (wishlistData?.data ?? [])
      .map(wishlistItemId)
      .filter((id): id is number => id !== undefined);

    const compareProductIds = (compareData?.products ?? []).map((p) => p.id);

    return {
      wishlistProductIds,
      compareProductIds,
    };
  }, [wishlistData, compareData]);

  /**
   * Иконка и счётчик меняются сразу, не дожидаясь сервера; при ошибке SWR откатывает
   * кэш, счётчик откатываем сами. Список заново не скачивается — market-docs/29.
   */
  const toggleWishlist = useCallback(
    async (productId: number) => {
      const on = !data.wishlistProductIds.includes(productId);
      bumpWishlistCount(on ? 1 : -1);
      try {
        let confirmed = on;
        await mutateWishlist(
          async (current) => {
            const response = await api.wishlist.toggle(productId);
            confirmed = response.in_wishlist;
            return withWishlist(current, productId, confirmed);
          },
          {
            optimisticData: (current) => withWishlist(current, productId, on),
            rollbackOnError: true,
            revalidate: false,
          },
        );
        // Сервер решил иначе (товар уже был в списке с другой вкладки) — сверяем счётчик
        if (confirmed !== on) void refreshWishlistCount();
      } catch (error) {
        bumpWishlistCount(on ? -1 : 1);
        console.error('Failed to toggle favorite:', error);
      }
    },
    [data.wishlistProductIds, mutateWishlist, bumpWishlistCount, refreshWishlistCount],
  );

  const toggleCompare = useCallback(
    async (productId: number) => {
      const on = !data.compareProductIds.includes(productId);
      bumpCompareCount(on ? 1 : -1);
      try {
        await mutateCompare(
          async (current) => {
            if (on) await api.compare.add(productId);
            else await api.compare.remove(productId);
            return withCompare(current, productId, on);
          },
          {
            optimisticData: (current) => withCompare(current, productId, on),
            rollbackOnError: true,
            revalidate: false,
          },
        );
      } catch (error) {
        bumpCompareCount(on ? -1 : 1);
        const e = error as { status?: number; data?: { message?: string } };
        if (e.status === 422) {
          alert(e.data?.message || 'Не удалось добавить товар в сравнение.');
        } else {
          console.error('Failed to toggle compare:', error);
        }
      }
    },
    [data.compareProductIds, mutateCompare, bumpCompareCount],
  );

  return {
    ...data,
    isLoading: !wishlistData && !compareData,
    mutateWishlist,
    mutateCompare,
    toggleWishlist,
    toggleCompare,
  };
}
