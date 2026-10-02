import { useCallback, useEffect, useRef } from 'react';
import useSWR, { useSWRConfig } from 'swr';
import type { Cache, ScopedMutator } from 'swr';
import { api } from '../lib/api';
import { runSessionTask } from '../lib/session-queue';
import { createIntentSync } from '../lib/intent-sync';
import type { Cart, CartItem } from '../lib/api';
import { useCounters } from './useCounters';
import { useRegion } from './useRegion';
import { useCartToast } from '../contexts/cart-toast-context';
import { getCartErrorMessage } from '../utils/cartErrors';
import {
  findCartLineForProduct,
  mergeCartItem,
  mergeServerLine,
  patchCartQuantity,
  setLineQuantity,
} from '../utils/cartProduct';

interface CartSync {
  quantities: ReturnType<typeof createIntentSync<number>>;
  previews: Map<number, Partial<CartItem>>;
  /** id строки на сервере: экран может уже не показывать строку, а удалить её надо */
  lineIds: Map<number, number>;
  /** Значения последнего рендера хука: ключ кэша зависит от региона */
  context: {
    cartKey: string;
    regionId: number | null;
    showCartToast: (message: string, type?: 'success' | 'error') => void;
  };
}

/** 404 на удаление — строки уже нет, этого и хотели */
async function ensureRemoved(request: Promise<unknown>): Promise<void> {
  try {
    await request;
  } catch (error) {
    if ((error as { status?: number }).status !== 404) throw error;
  }
}

/**
 * Количество в корзине по товару: экран сразу, на сервер — итог по очереди сессии.
 * Один синхронизатор на кэш SWR. market-docs/32
 */
const cartSyncs = new WeakMap<Cache, CartSync>();

function getCartSync(cache: Cache, mutate: ScopedMutator): CartSync {
  const existing = cartSyncs.get(cache);
  if (existing) return existing;

  const updateCart = (updater: (cart: Cart | undefined) => Cart | null | undefined) =>
    void mutate<Cart>(sync.context.cartKey, (cart) => updater(cart) ?? cart, { revalidate: false });

  const sync: CartSync = {
    previews: new Map(),
    lineIds: new Map(),
    context: { cartKey: '/api/cart', regionId: null, showCartToast: () => {} },
    quantities: createIntentSync<number>({
      send: async (productId, quantity, confirmed) => {
        const regionId = sync.context.regionId ?? undefined;
        // Сервер пересоздаёт строку на каждое изменение — id берём из последнего ответа
        const lineId = sync.lineIds.get(productId) ?? 0;
        if (quantity === 0) {
          if (lineId > 0) await ensureRemoved(api.cart.remove(lineId));
          sync.lineIds.delete(productId);
          return 0;
        }
        // POST суммирует с тем, что уже в корзине, PUT ставит количество
        const result =
          lineId > 0
            ? await api.cart.update(lineId, { quantity, region_id: regionId })
            : await api.cart.add({ product_id: productId, quantity: quantity - confirmed, region_id: regionId });
        sync.lineIds.set(productId, result.item.id);
        if (result.was_adjusted) sync.context.showCartToast(result.message, 'success');
        updateCart((cart) => mergeServerLine(cart, result.item));
        return result.item.quantity;
      },
      apply: (productId, quantity) =>
        updateCart((cart) => setLineQuantity(cart, productId, quantity, sync.previews.get(productId))),
      onError: (_productId, error) => sync.context.showCartToast(getCartErrorMessage(error), 'error'),
    }),
  };
  cartSyncs.set(cache, sync);
  return sync;
}

export function useCart() {
  const { setCartCount } = useCounters();
  const { getRegionId } = useRegion();
  const regionIdRef = useRef<number | null>(null);
  regionIdRef.current = getRegionId();

  const regionId = regionIdRef.current;
  const cartKey = regionId ? `/api/cart?region_id=${regionId}` : '/api/cart';
  const { cache, mutate } = useSWRConfig();
  const cartSync = getCartSync(cache, mutate);
  cartSync.context.cartKey = cartKey;
  cartSync.context.regionId = regionId;
  cartSync.context.showCartToast = useCartToast().showCartToast;

  const {
    data: cart,
    error,
    isLoading,
    mutate: mutateCart,
  } = useSWR(cartKey, () => api.cart.get(regionId ? { region_id: regionId } : undefined), {
    revalidateOnFocus: false,
    revalidateOnReconnect: true,
    revalidateIfStale: false,
    keepPreviousData: true,
    dedupingInterval: 5000,
  });

  useEffect(() => {
    if (typeof cart?.item_count === 'number') {
      setCartCount(cart.item_count);
    }
  }, [cart?.item_count, setCartCount]);

  const syncCartFromServer = useCallback(async () => {
    const rid = regionIdRef.current;
    const fresh = await api.cart.get(rid ? { region_id: rid } : undefined);
    await mutateCart(fresh, { revalidate: false });
    return fresh;
  }, [mutateCart]);

  const applyCartToCache = useCallback(
    async (updater: (current: Cart | undefined) => Cart | null | undefined) => {
      return mutateCart(
        (current) => {
          const next = updater(current);
          if (next === undefined || next === null) return current;
          return next;
        },
        { revalidate: false },
      );
    },
    [mutateCart],
  );

  const addToCart = useCallback(
    (
      productId: number,
      quantity: number = 1,
      variationAttributes?: { attribute_slug: string; value_slug: string }[],
      preview?: Partial<CartItem>,
    ) =>
      runSessionTask(async () => {
        await applyCartToCache((current) =>
          patchCartQuantity(current ?? null, productId, quantity, preview),
        );

        try {
          const rid = regionIdRef.current;
          const result = await api.cart.add({
            product_id: productId,
            quantity,
            region_id: rid ?? undefined,
            ...(variationAttributes?.length ? { variation_attributes: variationAttributes } : {}),
          });

          await applyCartToCache((current) => mergeCartItem(current ?? null, result.item));

          return result;
        } catch (err) {
          await syncCartFromServer();
          throw err;
        }
      }),
    [applyCartToCache, syncCartFromServer],
  );

  const updateQuantity = useCallback(
    (itemId: number, quantity: number) =>
      runSessionTask(async () => {
        await applyCartToCache((current) => {
          const item = current?.items?.find((i) => i.id === itemId);
          if (!item) return current;
          const delta = quantity - item.quantity;
          if (delta === 0) return current;
          return patchCartQuantity(current ?? null, item.product_id, delta);
        });

        try {
          const rid = regionIdRef.current;
          const result = await api.cart.update(itemId, {
            quantity,
            region_id: rid ?? undefined,
          });
          await applyCartToCache((current) => mergeCartItem(current ?? null, result.item));
          return result;
        } catch (err) {
          await syncCartFromServer();
          throw err;
        }
      }),
    [applyCartToCache, syncCartFromServer],
  );

  const updateVariant = useCallback(
    (
      itemId: number,
      quantity: number,
      variationAttributes?: { attribute_slug: string; value_slug: string }[],
    ) =>
      runSessionTask(async () => {
        try {
          const rid = regionIdRef.current;
          const result = await api.cart.update(itemId, {
            quantity,
            region_id: rid ?? undefined,
            ...(variationAttributes?.length ? { variation_attributes: variationAttributes } : {}),
          });
          await applyCartToCache((current) => mergeCartItem(current ?? null, result.item));
          return result;
        } catch (err) {
          await syncCartFromServer();
          throw err;
        }
      }),
    [applyCartToCache, syncCartFromServer],
  );

  const removeFromCart = useCallback(
    (itemId: number) =>
      runSessionTask(async () => {
        await applyCartToCache((current) => {
          const item = current?.items?.find((i) => i.id === itemId);
          if (!item) return current;
          return patchCartQuantity(current ?? null, item.product_id, -item.quantity);
        });

        try {
          await api.cart.remove(itemId);
          await syncCartFromServer();
        } catch (err) {
          await syncCartFromServer();
          throw err;
        }
      }),
    [applyCartToCache, syncCartFromServer],
  );

  const clearCart = useCallback(
    () =>
      runSessionTask(async () => {
        await applyCartToCache(() => ({
          items: [],
          subtotal: 0,
          item_count: 0,
          is_empty: true,
        }));

        try {
          await api.cart.clear();
        } catch (err) {
          await syncCartFromServer();
          throw err;
        }
      }),
    [applyCartToCache, syncCartFromServer],
  );

  /** Поставить количество товара; клики копятся, на сервер уходит итог — market-docs/32 */
  const setCartQuantity = useCallback(
    (
      productId: number,
      quantity: number,
      options?: { preview?: Partial<CartItem>; /** остаток; нет — без ограничения */ max?: number },
    ) => {
      const target = Math.max(0, options?.max === undefined ? quantity : Math.min(quantity, options.max));
      if (options?.preview) cartSync.previews.set(productId, options.preview);
      const shown = findCartLineForProduct(cache.get(cartKey)?.data as Cart | undefined, productId);
      if (shown && shown.id > 0) cartSync.lineIds.set(productId, shown.id);
      const current = shown?.quantity ?? 0;
      cartSync.quantities.set(productId, target, current);
    },
    [cache, cartKey, cartSync],
  );

  /** «+» / «−»: от того, что сейчас на экране, включая ещё не отправленные клики */
  const adjustCartQuantity = useCallback(
    (productId: number, delta: number, options?: { preview?: Partial<CartItem>; max?: number }) => {
      const shown = findCartLineForProduct(cache.get(cartKey)?.data as Cart | undefined, productId);
      setCartQuantity(productId, (shown?.quantity ?? 0) + delta, options);
    },
    [cache, cartKey, setCartQuantity],
  );

  const loadCart = useCallback(async () => {
    await syncCartFromServer();
  }, [syncCartFromServer]);

  return {
    cart: cart || null,
    isLoading,
    error: error ? (error instanceof Error ? error.message : 'Ошибка загрузки корзины') : null,
    addToCart,
    adjustCartQuantity,
    setCartQuantity,
    updateQuantity,
    updateVariant,
    removeFromCart,
    clearCart,
    reloadCart: loadCart,
  };
}
