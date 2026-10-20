import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../../../hooks/useCart';
import type { ProductDetail, ProductVariant } from '../../../lib/api';

const ERROR_MS = 5000;

type CartAttributes = { attribute_slug: string; value_slug: string }[] | undefined;

interface Options {
  product: ProductDetail;
  /** Вариант, найденный по выбору атрибутов (без ручного выбора цвета) */
  matchedVariant: ProductVariant | null;
  /** Вариант, который сейчас показан: по нему ищем строку корзины */
  selectedVariant: ProductVariant | null;
  cartAttributes: () => CartAttributes;
}

const messageOf = (err: any, fallback: string) => {
  const msg = err?.data?.message || (err instanceof Error ? err.message : fallback);
  return typeof msg === 'string' ? msg : fallback;
};

/** Корзина со страницы товара: добавить, «купить сейчас», ± количество, сообщение об ошибке */
export function useProductCartActions({
  product,
  matchedVariant,
  selectedVariant,
  cartAttributes,
}: Options) {
  const navigate = useNavigate();
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
  const cartQuantity = cartItem?.quantity ?? 0;

  /** У вариативного товара без подходящего варианта купить нечего */
  const selectionMissing = () => {
    if (!(product.is_variable && !product.is_variant && product.variation_attributes?.length)) {
      return false;
    }
    const attrs = cartAttributes();
    return !attrs || attrs.length === 0 || !matchedVariant;
  };

  const add = async () => {
    if (selectionMissing()) return showError('Выберите доступную комбинацию параметров');

    let available = product.in_stock === true;
    if (product.is_variable && !product.is_variant && product.variants) {
      available = matchedVariant
        ? matchedVariant.in_stock === true && (matchedVariant.stock ?? 0) > 0
        : false;
    }
    if (!available) return showError('Товар недоступен для заказа');

    try {
      setIsBusy(true);
      setError(null);
      const result = await addToCart(product.id, 1, cartAttributes());
      if (result.was_adjusted) alert(result.message);
    } catch (err: any) {
      console.error('Failed to add to cart:', err);
      const apiMessage = err?.data?.message;
      if (typeof apiMessage === 'string') showError(apiMessage);
      else if (err?.status === 404 || String(err?.message ?? '').includes('404'))
        showError('Выбранная комбинация недоступна. Проверьте параметры.');
      else if (String(err?.message ?? '').includes('422')) showError('Товар недоступен для заказа');
      else showError(err instanceof Error ? err.message : 'Ошибка добавления в корзину');
    } finally {
      setIsBusy(false);
    }
  };

  const buyNow = async () => {
    if (selectionMissing()) return showError('Выберите доступную комбинацию параметров');
    try {
      setIsBusy(true);
      setError(null);
      await addToCart(product.id, 1, cartAttributes());
      navigate('/cart');
    } catch (err: any) {
      console.error('Failed to add to cart:', err);
      setError(messageOf(err, 'Ошибка добавления в корзину'));
    } finally {
      setIsBusy(false);
    }
  };

  const changeQuantity = async (delta: 1 | -1) => {
    try {
      setIsBusy(true);
      if (!cartItem) {
        if (delta > 0) await add();
        return;
      }
      const next = cartItem.quantity + delta;
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
    cartQuantity,
    isBusy,
    error,
    add,
    buyNow,
    increment: () => changeQuantity(1),
    decrement: () => changeQuantity(-1),
    goToCheckout: () => navigate('/checkout'),
  };
}
