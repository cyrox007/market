import { useCallback, useRef } from 'react';
import { useCart } from './useCart';
import { useCartToast } from '../contexts/CartToastContext';
import { getCartErrorMessage } from '../utils/cartErrors';
import {
  isVariableParent,
  productPreviewFromProduct,
  resolveProductIdForCart,
} from '../utils/cartProduct';
import type { Product } from '../lib/api';

const TOAST_DEBOUNCE_MS = 1200;

type CartProductInput = Pick<
  Product,
  | 'id'
  | 'name'
  | 'slug'
  | 'price'
  | 'is_variable'
  | 'is_variant'
  | 'first_available_variant_id'
  | 'in_stock'
> & {
  thumbnail?: string | null;
  image?: string | null;
  sku?: string;
};

/**
 * Добавление в корзину с toast и единой обработкой ошибок.
 */
export function useCartActions() {
  const cart = useCart();
  const { showCartToast } = useCartToast();
  const successToastTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pendingSuccessRef = useRef<{ message: string } | null>(null);

  const scheduleSuccessToast = useCallback(
    (message: string) => {
      pendingSuccessRef.current = { message };
      if (successToastTimerRef.current) {
        clearTimeout(successToastTimerRef.current);
      }
      successToastTimerRef.current = setTimeout(() => {
        const pending = pendingSuccessRef.current;
        if (pending) {
          showCartToast(pending.message, 'success');
          pendingSuccessRef.current = null;
        }
        successToastTimerRef.current = null;
      }, TOAST_DEBOUNCE_MS);
    },
    [showCartToast],
  );

  const addProductToCart = useCallback(
    async (
      product: CartProductInput,
      quantity: number = 1,
      variationAttributes?: { attribute_slug: string; value_slug: string }[],
    ) => {
      if (isVariableParent(product) && !variationAttributes?.length) {
        showCartToast('Выберите параметры на странице товара', 'error');
        return null;
      }
      if (!product.in_stock) {
        showCartToast('Товар недоступен для заказа', 'error');
        return null;
      }

      const productId = resolveProductIdForCart(product);
      const preview = productPreviewFromProduct(product);

      try {
        const result = await cart.addToCart(productId, quantity, variationAttributes, preview);
        scheduleSuccessToast(result.was_adjusted ? result.message : 'Товар добавлен в корзину');
        return result;
      } catch (error) {
        if (successToastTimerRef.current) {
          clearTimeout(successToastTimerRef.current);
          successToastTimerRef.current = null;
          pendingSuccessRef.current = null;
        }
        showCartToast(getCartErrorMessage(error), 'error');
        throw error;
      }
    },
    [cart, showCartToast, scheduleSuccessToast],
  );

  const addProductIdToCart = useCallback(
    async (productId: number, quantity: number = 1) => {
      try {
        const result = await cart.addToCart(productId, quantity);
        showCartToast(result.was_adjusted ? result.message : 'Товар добавлен в корзину', 'success');
        return result;
      } catch (error) {
        showCartToast(getCartErrorMessage(error), 'error');
        throw error;
      }
    },
    [cart, showCartToast],
  );

  const changeProductQuantity = useCallback(
    async (product: CartProductInput, delta: number) => {
      if (isVariableParent(product) && delta > 0) {
        showCartToast('Выберите параметры на странице товара', 'error');
        return null;
      }

      const productId = resolveProductIdForCart(product);
      const preview = productPreviewFromProduct(product);

      try {
        const result = await cart.adjustCartQuantity(productId, delta, preview);
        if (delta > 0 && result) {
          scheduleSuccessToast(result.was_adjusted ? result.message : 'Количество обновлено');
        }
        return result;
      } catch (error) {
        showCartToast(getCartErrorMessage(error), 'error');
        throw error;
      }
    },
    [cart, showCartToast, scheduleSuccessToast],
  );

  return {
    ...cart,
    addProductToCart,
    addProductIdToCart,
    changeProductQuantity,
  };
}
