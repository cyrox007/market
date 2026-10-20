import { useEffect, useRef, useState } from 'react';
import { useCart } from '../../../hooks/useCart';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import type { BuyBlockCart } from '../components/BuyBlock';
import { availabilityOf } from '../lib/availability';

const ERROR_MS = 5000;

type CartAttributes = { attribute_slug: string; value_slug: string }[] | undefined;

interface Options {
  product: ProductDetail;
  /** Выбранный вариант: по нему наличие и строка корзины */
  selectedVariant: ProductVariant | null;
  cartAttributes: () => CartAttributes;
}

const messageOf = (err: any, fallback: string) => {
  const msg = err?.data?.message || (err instanceof Error ? err.message : fallback);
  return typeof msg === 'string' ? msg : fallback;
};

/** Корзина со страницы товара: добавить, ± количество (до остатка), сообщение об ошибке */
export function useProductCartActions({
  product,
  selectedVariant,
  cartAttributes,
}: Options): BuyBlockCart {
  const { cart, addToCart, updateQuantity, removeFromCart } = useCart();
  const [isBusy, setIsBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  useEffect(() => () => clearTimeout(timer.current), []);

  const showError = (message: string) => {
    setError(message);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setError(null), ERROR_MS);
  };

  const cartProductId = selectedVariant?.id ?? product.id;
  const cartItem = (cart?.items ?? []).find((item) => item.product_id === cartProductId);
  const availability = availabilityOf(selectedVariant ?? product);
  const isVariableParent = Boolean(product.is_variable && !product.is_variant);

  const add = async () => {
    if (isVariableParent && !selectedVariant) {
      return showError('Выберите доступную комбинацию параметров');
    }
    if (availability.status === 'out') return showError('Товар недоступен для заказа');

    try {
      setIsBusy(true);
      setError(null);
      const result = await addToCart(product.id, 1, cartAttributes());
      if (result.was_adjusted) showError(result.message);
    } catch (err: any) {
      console.error('Failed to add to cart:', err);
      const apiMessage = err?.data?.message;
      if (typeof apiMessage === 'string') showError(apiMessage);
      else if (err?.status === 404)
        showError('Выбранная комбинация недоступна. Проверьте параметры.');
      else if (err?.status === 422) showError('Товар недоступен для заказа');
      else showError(messageOf(err, 'Ошибка добавления в корзину'));
    } finally {
      setIsBusy(false);
    }
  };

  const changeQuantity = async (delta: 1 | -1) => {
    if (!cartItem) {
      if (delta > 0) await add();
      return;
    }
    const next = cartItem.quantity + delta;
    if (availability.maxQuantity !== undefined && next > availability.maxQuantity) return;
    try {
      setIsBusy(true);
      if (next <= 0) await removeFromCart(cartItem.id);
      else await updateQuantity(cartItem.id, next);
    } catch (err: any) {
      console.error('Failed to change cart quantity:', err);
      showError(messageOf(err, 'Ошибка изменения количества'));
    } finally {
      setIsBusy(false);
    }
  };

  return {
    quantity: cartItem?.quantity ?? 0,
    isBusy,
    error,
    add: () => void add(),
    increment: () => void changeQuantity(1),
    decrement: () => void changeQuantity(-1),
  };
}
