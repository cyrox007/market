/**
 * Хук для загрузки wishlist и compare с использованием SWR
 * Централизует загрузку этих данных и предотвращает дублирование запросов
 */

import { useCallback, useMemo } from 'react';
import useSWR, { useSWRConfig } from 'swr';
import type { Cache, ScopedMutator } from 'swr';
import { api } from '../lib/api';
import { createIntentSync } from '../lib/intent-sync';
import { onIdle, runSessionRead } from '../lib/session-queue';
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

const WISHLIST_KEY = '/api/wishlist';
const COMPARE_KEY = '/api/compare';

interface Counters {
  bumpWishlistCount: (delta: number) => void;
  bumpCompareCount: (delta: number) => void;
}

type ListSync = ReturnType<typeof createIntentSync<boolean>>;

interface Syncs {
  wishlist: ListSync;
  compare: ListSync;
  counters: Counters;
  /** Какие списки менялись — их сверить с сервером, когда очередь опустеет */
  dirty: { wishlist: boolean; compare: boolean };
}

/**
 * Один синхронизатор на кэш SWR: все карточки страницы делят очередь и состояние.
 * Кэш правится точечно по одному товару — параллельные клики не затирают друг друга.
 * market-docs/32
 */
/** Сервер уже в нужном состоянии: 422 «уже есть» на добавление, 404 «нет» на удаление */
async function ensure(
  request: Promise<unknown>,
  alreadyStatus: number,
  isAlready: (error: ApiError) => boolean = () => true,
): Promise<void> {
  try {
    await request;
  } catch (error) {
    const e = error as ApiError;
    if (e.status !== alreadyStatus || !isAlready(e)) throw error;
  }
}

interface ApiError {
  status?: number;
  data?: { message?: string };
}

/** У сравнения 422 значит и «уже в списке», и «лимит 5» — различаем по тексту. market-docs/15 §9 */
const alreadyInCompare = (error: ApiError) => /уже/i.test(error.data?.message ?? '');

/** Как в CompareController::add */
const COMPARE_LIMIT = 5;
const COMPARE_LIMIT_MESSAGE = 'Максимум 5 товаров для сравнения';

const syncsByCache = new WeakMap<Cache, Syncs>();

function getSyncs(cache: Cache, mutate: ScopedMutator): Syncs {
  const existing = syncsByCache.get(cache);
  if (existing) return existing;

  const syncs: Syncs = {
    counters: { bumpWishlistCount: () => {}, bumpCompareCount: () => {} },
    dirty: { wishlist: false, compare: false },
    wishlist: createIntentSync<boolean>({
      send: async (id, on) => {
        syncs.dirty.wishlist = true;
        if (on) await ensure(api.wishlist.add(id), 422);
        else await ensure(api.wishlist.remove(id), 404);
        return on;
      },
      apply: (id, on) => {
        const current = cache.get(WISHLIST_KEY)?.data as WishlistData | undefined;
        const has = (current?.data ?? []).some((item) => wishlistItemId(item) === id);
        if (has === on) return;
        void mutate<WishlistData>(WISHLIST_KEY, (data) => withWishlist(data, id, on), { revalidate: false });
        syncs.counters.bumpWishlistCount(on ? 1 : -1);
      },
      onError: (_id, error) => console.error('Failed to update favorite:', error),
    }),
    compare: createIntentSync<boolean>({
      send: async (id, on) => {
        syncs.dirty.compare = true;
        if (on) await ensure(api.compare.add(id), 422, alreadyInCompare);
        else await ensure(api.compare.remove(id), 404);
        return on;
      },
      apply: (id, on) => {
        const current = cache.get(COMPARE_KEY)?.data as CompareData | undefined;
        const has = (current?.products ?? []).some((p) => p.id === id);
        if (has === on) return;
        void mutate<CompareData>(COMPARE_KEY, (data) => withCompare(data, id, on), { revalidate: false });
        syncs.counters.bumpCompareCount(on ? 1 : -1);
      },
      onError: (_id, error) => {
        const e = error as ApiError;
        if (e.status === 422) alert(e.data?.message || 'Не удалось добавить товар в сравнение.');
        else console.error('Failed to update compare:', error);
      },
    }),
  };
  // Сверка: кэш и счётчик = сервер. Если за время запроса кликнули — дождёмся следующей
  const reconcile = <T,>(
    key: string,
    sync: ListSync,
    load: () => Promise<T>,
    size: (data: T | undefined) => number,
    bump: () => (delta: number) => void,
  ) =>
    runSessionRead(load)
      .then((fresh) => {
        if (sync.hasPending()) return;
        const delta = size(fresh) - size(cache.get(key)?.data as T | undefined);
        void mutate(key, fresh, { revalidate: false });
        if (delta !== 0) bump()(delta);
      })
      .catch(() => {});

  onIdle(() => {
    if (syncs.dirty.wishlist) {
      syncs.dirty.wishlist = false;
      void reconcile<WishlistData>(
        WISHLIST_KEY,
        syncs.wishlist,
        () => api.wishlist.list(),
        (data) => data?.data.length ?? 0,
        () => syncs.counters.bumpWishlistCount,
      );
    }
    if (syncs.dirty.compare) {
      syncs.dirty.compare = false;
      void reconcile<CompareData>(
        COMPARE_KEY,
        syncs.compare,
        () => api.compare.list(),
        (data) => data?.products.length ?? 0,
        () => syncs.counters.bumpCompareCount,
      );
    }
  });
  syncsByCache.set(cache, syncs);
  return syncs;
}

/**
 * Хук для получения списков wishlist и compare
 */
export function useWishlistAndCompare() {
  const { bumpWishlistCount, bumpCompareCount } = useCounters();
  const { cache, mutate } = useSWRConfig();
  const syncs = getSyncs(cache, mutate);
  syncs.counters.bumpWishlistCount = bumpWishlistCount;
  syncs.counters.bumpCompareCount = bumpCompareCount;

  const { data: wishlistData, mutate: mutateWishlist } = useSWR<WishlistData>(
    WISHLIST_KEY,
    () => runSessionRead(() => api.wishlist.list()).catch(() => ({ data: [] })),
    {
      revalidateOnFocus: false,
      revalidateIfStale: false,
      dedupingInterval: 5000,
    },
  );

  const { data: compareData, mutate: mutateCompare } = useSWR<CompareData>(
    COMPARE_KEY,
    () => runSessionRead(() => api.compare.list()).catch(() => ({ products: [] })),
    {
      revalidateOnFocus: false,
      revalidateIfStale: false,
      dedupingInterval: 5000,
    },
  );

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

  /** Иконка и счётчик меняются сразу, на сервер уходит итог по очереди — market-docs/29, 32 */
  const toggleWishlist = useCallback(
    async (productId: number) => {
      const on = !data.wishlistProductIds.includes(productId);
      syncs.wishlist.set(productId, on, !on);
    },
    [data.wishlistProductIds, syncs],
  );

  const toggleCompare = useCallback(
    async (productId: number) => {
      const on = !data.compareProductIds.includes(productId);
      if (on && data.compareProductIds.length >= COMPARE_LIMIT) {
        alert(COMPARE_LIMIT_MESSAGE);
        return;
      }
      syncs.compare.set(productId, on, !on);
    },
    [data.compareProductIds, syncs],
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
