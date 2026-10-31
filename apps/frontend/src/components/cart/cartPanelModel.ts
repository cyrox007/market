import type { CartItem } from '../../lib/api';
import { availabilityOf } from '../../lib/availability';
import { plural } from '../../lib/plural';
import { isUnconfiguredKit } from '../../utils/cartProduct';

export const cartQuantityLabel = (quantity: number) =>
  `${quantity} ${plural(quantity, ['товар', 'товара', 'товаров'])}`;

export function cartItemAvailability(item: CartItem) {
  if (!item.product_id || isUnconfiguredKit(item)) return availabilityOf({ in_stock: false });
  // CartItemResource отдаёт stock, но не in_stock; optimistic preview может содержать оба.
  return availabilityOf({ ...item, in_stock: item.in_stock ?? Number(item.stock) > 0 });
}

export function cartPanelTotals(items: CartItem[]) {
  return items.reduce(
    (result, item) => {
      const available = cartItemAvailability(item).status !== 'out';
      return {
        quantity: result.quantity + item.quantity,
        subtotal: result.subtotal + (available ? item.total : 0),
        hasUnavailable: result.hasUnavailable || !available,
      };
    },
    { quantity: 0, subtotal: 0, hasUnavailable: false },
  );
}
