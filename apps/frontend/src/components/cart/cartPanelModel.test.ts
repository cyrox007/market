import { describe, expect, it } from 'vitest';
import type { CartItem } from '../../lib/api';
import { cartItemAvailability, cartPanelTotals, cartQuantityLabel } from './cartPanelModel';

const item = (overrides: Partial<CartItem> = {}): CartItem => ({
  id: 1,
  product_id: 1,
  name: 'Шкаф',
  slug: 'shkaf',
  price: 100,
  quantity: 2,
  total: 200,
  image: null,
  sku: null,
  stock: 3,
  ...overrides,
});

describe('модель панели корзины', () => {
  it('использует остаток строки API, в котором нет in_stock', () => {
    expect(cartItemAvailability(item())).toMatchObject({ status: 'in_stock', maxQuantity: 3 });
    expect(cartItemAvailability(item({ stock: 0, available_to_order: true }))).toMatchObject({
      status: 'backorder',
      maxQuantity: undefined,
    });
    expect(
      cartItemAvailability(item({ stock: 3, available_to_order: true })).maxQuantity,
    ).toBeUndefined();
  });
  it('блокирует ненастроенный комплект, но не обычный товар с таким флагом', () => {
    expect(
      cartItemAvailability(item({ is_sellable_bundle: true, is_bundle_configured: false })).status,
    ).toBe('out');
    expect(cartItemAvailability(item({ is_bundle_configured: false })).status).toBe('in_stock');
  });
  it('не включает недоступные строки в сумму и блокирует оформление', () => {
    expect(cartPanelTotals([item(), item({ id: 2, stock: 0 })])).toEqual({
      quantity: 4,
      subtotal: 200,
      hasUnavailable: true,
    });
    expect(cartPanelTotals([])).toEqual({ quantity: 0, subtotal: 0, hasUnavailable: false });
  });
  it.each([
    [1, '1 товар'],
    [2, '2 товара'],
    [5, '5 товаров'],
    [11, '11 товаров'],
    [21, '21 товар'],
  ])('склоняет %s', (count, label) => {
    expect(cartQuantityLabel(Number(count))).toBe(label);
  });
});
