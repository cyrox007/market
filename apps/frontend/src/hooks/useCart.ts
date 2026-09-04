import { useCallback, useEffect, useRef } from 'react';
import useSWR from 'swr';
import { api } from '../lib/api';
import { runCartMutation } from '../lib/cart-mutation-queue';
import type { Cart, CartItem } from '../lib/api';
import { useCounters } from './useCounters';
import { useRegion } from './useRegion';
import { findCartLineForProduct, mergeCartItem, patchCartQuantity } from '../utils/cartProduct';

export function useCart() {
  const { refreshCartCount, setCartCount } = useCounters();
  const { getRegionId } = useRegion();
  const regionIdRef = useRef<number | null>(null);
  regionIdRef.current = getRegionId();

  const regionId = regionIdRef.current;
  const cartKey = regionId ? `/api/cart?region_id=${regionId}` : '/api/cart';

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
      runCartMutation(async () => {
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
      runCartMutation(async () => {
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
      runCartMutation(async () => {
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
      runCartMutation(async () => {
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
      runCartMutation(async () => {
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

  /**
   * Изменить количество по product_id, читая актуальный кэш внутри очереди.
   * «+» идёт через POST /cart (сервер суммирует), «−» через PUT/DELETE с реальным item.id.
   */
  const adjustCartQuantity = useCallback(
    (productId: number, delta: number, preview?: Partial<CartItem>) =>
      runCartMutation(async () => {
        if (delta === 0) return;

        if (delta > 0) {
          await applyCartToCache((current) =>
            patchCartQuantity(current ?? null, productId, delta, preview),
          );

          try {
            const rid = regionIdRef.current;
            const result = await api.cart.add({
              product_id: productId,
              quantity: delta,
              region_id: rid ?? undefined,
            });
            await applyCartToCache((current) => mergeCartItem(current ?? null, result.item));
            return result;
          } catch (err) {
            await syncCartFromServer();
            throw err;
          }
        }

        // delta < 0
        let serverItemId: number | undefined;
        let newQty = 0;

        await applyCartToCache((current) => {
          const line = findCartLineForProduct(current ?? null, productId);
          if (!line || line.id <= 0) return current;
          serverItemId = line.id;
          newQty = line.quantity + delta;
          return patchCartQuantity(current ?? null, productId, delta);
        });

        if (!serverItemId) {
          await syncCartFromServer();
          return;
        }

        try {
          const rid = regionIdRef.current;
          if (newQty <= 0) {
            await api.cart.remove(serverItemId);
            await syncCartFromServer();
            return;
          }

          const result = await api.cart.update(serverItemId, {
            quantity: newQty,
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

  const loadCart = useCallback(async () => {
    await syncCartFromServer();
  }, [syncCartFromServer]);

  return {
    cart: cart || null,
    isLoading,
    error: error ? (error instanceof Error ? error.message : 'Ошибка загрузки корзины') : null,
    addToCart,
    adjustCartQuantity,
    updateQuantity,
    updateVariant,
    removeFromCart,
    clearCart,
    reloadCart: loadCart,
  };
}
