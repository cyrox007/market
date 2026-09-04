import type { Cart, CartItem, Product } from '../lib/api';

/** ID товара для POST /cart (вариация, если родитель вариативный) */
export function resolveProductIdForCart(product: {
  id: number;
  is_variable?: boolean;
  is_variant?: boolean;
  first_available_variant_id?: number | null;
}): number {
  if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
    return product.first_available_variant_id;
  }
  return product.id;
}

export function isVariableParent(product: {
  id?: number;
  is_variable?: boolean;
  is_variant?: boolean;
  first_available_variant_id?: number | null;
  variation_attributes?: unknown[] | null;
  variants?: unknown[] | null;
}): boolean {
  if (product.is_variant === true) return false;
  if (product.is_variable === true) return true;
  if (
    product.first_available_variant_id != null &&
    product.id != null &&
    product.first_available_variant_id !== product.id
  ) {
    return true;
  }
  if (Array.isArray(product.variation_attributes) && product.variation_attributes.length > 0) {
    return true;
  }
  if (Array.isArray(product.variants) && product.variants.length > 0) return true;
  return false;
}

/** API иногда отдаёт is_variable:false у вариативного родителя — нормализуем для карточек */
export function normalizeListProduct<
  T extends {
    id: number;
    is_variable?: boolean;
    is_variant?: boolean;
    first_available_variant_id?: number | null;
  },
>(product: T): T {
  if (isVariableParent(product) && product.is_variable !== true) {
    return { ...product, is_variable: true, is_variant: false };
  }
  return product;
}

/** Количество в корзине по карточке каталога (учитывает id вариации) */
/** Строка корзины с реальным id с сервера (не временный optimistic id) */
export function findCartLineForProduct(
  cart: Cart | null | undefined,
  productId: number,
  variantId?: number | null,
): CartItem | undefined {
  if (!cart?.items?.length) return undefined;
  const ids = new Set<number>([productId]);
  if (variantId) ids.add(variantId);

  const matches = cart.items.filter((item) => ids.has(item.product_id));
  const withServerId = matches.filter((item) => item.id > 0);
  const pool = withServerId.length > 0 ? withServerId : matches;
  return pool[0];
}

export function productPreviewFromProduct(product: {
  id: number;
  name: string;
  slug: string;
  price: number;
  thumbnail?: string | null;
  image?: string | null;
  sku?: string;
}): Partial<CartItem> {
  return {
    name: product.name,
    slug: product.slug,
    price: product.price,
    image: product.thumbnail ?? product.image ?? null,
    sku: product.sku,
  };
}

export function getCartQuantityForProduct(items: CartItem[] | undefined, product: Product): number {
  if (!items?.length) return 0;
  const ids = new Set<number>([product.id]);
  if (product.first_available_variant_id) {
    ids.add(product.first_available_variant_id);
  }
  return items
    .filter((item) => ids.has(item.product_id))
    .reduce((sum, item) => sum + item.quantity, 0);
}

/**
 * Подмешивает позицию с сервера; не откатывает количество, если в кэше уже больше
 * (защита от устаревшего ответа при гонке).
 */
export function mergeCartItem(cart: Cart | null | undefined, item: CartItem): Cart {
  const base: Cart = cart ?? { items: [], subtotal: 0, item_count: 0, is_empty: true };
  // Убираем временные строки (отрицательный id) для того же product_id
  const items = base.items.filter((i) => !(i.product_id === item.product_id && i.id <= 0));
  const idx = items.findIndex((i) => i.product_id === item.product_id);
  if (idx >= 0) {
    const cachedQty = items[idx].quantity;
    if (item.quantity < cachedQty) {
      return base;
    }
    items[idx] = item;
  } else {
    items.unshift(item);
  }
  const subtotal = items.reduce((s, i) => s + (i.total ?? i.price * i.quantity), 0);
  const item_count = items.reduce((s, i) => s + i.quantity, 0);
  return {
    items,
    subtotal,
    item_count,
    is_empty: items.length === 0,
  };
}

export function patchCartQuantity(
  cart: Cart | null | undefined,
  productId: number,
  delta: number,
  preview?: Partial<CartItem>,
): Cart | null {
  if (!cart) {
    if (delta <= 0 || !preview) return null;
    const qty = delta;
    const price = preview.price ?? 0;
    const items: CartItem[] = [
      {
        id: preview.id ?? -Date.now(),
        product_id: productId,
        name: preview.name ?? '',
        slug: preview.slug ?? null,
        price,
        quantity: qty,
        total: price * qty,
        image: preview.image ?? null,
        sku: preview.sku ?? null,
      },
    ];
    return {
      items,
      subtotal: price * qty,
      item_count: qty,
      is_empty: false,
    };
  }
  const items = [...cart.items];
  const idx = items.findIndex((i) => i.product_id === productId);
  if (idx < 0 && delta <= 0) return cart;
  if (idx < 0 && delta > 0 && preview) {
    const qty = delta;
    const price = preview.price ?? 0;
    items.unshift({
      id: preview.id ?? -Date.now(),
      product_id: productId,
      name: preview.name ?? '',
      slug: preview.slug ?? null,
      price,
      quantity: qty,
      total: price * qty,
      image: preview.image ?? null,
      sku: preview.sku ?? null,
    });
  } else if (idx >= 0) {
    const nextQty = items[idx].quantity + delta;
    if (nextQty <= 0) {
      items.splice(idx, 1);
    } else {
      const price = items[idx].price;
      items[idx] = {
        ...items[idx],
        quantity: nextQty,
        total: price * nextQty,
      };
    }
  }
  const subtotal = items.reduce((s, i) => s + (i.total ?? i.price * i.quantity), 0);
  const item_count = items.reduce((s, i) => s + i.quantity, 0);
  return {
    items,
    subtotal,
    item_count,
    is_empty: items.length === 0,
  };
}
