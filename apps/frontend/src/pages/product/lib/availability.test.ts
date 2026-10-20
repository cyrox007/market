import { describe, expect, it } from 'vitest';
import { availabilityOf } from './availability';

describe('наличие варианта для региона', () => {
  it('остаток известен — «в наличии», число и предел корзины', () => {
    expect(availabilityOf({ in_stock: true, stock: 12, stock_known: true })).toEqual({
      status: 'in_stock',
      stock: 12,
      maxQuantity: 12,
    });
  });

  it('в наличии, но остаток неизвестен — без числа и без предела', () => {
    expect(availabilityOf({ in_stock: true, stock: null, stock_known: false })).toEqual({
      status: 'in_stock',
      stock: null,
      maxQuantity: undefined,
    });
  });

  it('остатка нет, но заказать можно — «под заказ» без предела', () => {
    expect(
      availabilityOf({ in_stock: false, stock: 0, stock_known: true, available_to_order: true }),
    ).toEqual({ status: 'backorder', stock: null, maxQuantity: undefined });
    expect(availabilityOf({ in_stock: false, stock: 0, backorder: true }).status).toBe('backorder');
  });

  it('остатка нет и заказать нельзя — «нет в наличии»', () => {
    expect(availabilityOf({ in_stock: false, stock: 0, available_to_order: false })).toEqual({
      status: 'out',
      stock: null,
      maxQuantity: 0,
    });
  });
});
