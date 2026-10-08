import { describe, expect, it } from 'vitest';
import { plural } from './plural';

const PRODUCTS = ['товар', 'товара', 'товаров'] as const;

describe('plural', () => {
  it('склоняет по числу', () => {
    expect([1, 2, 5, 11, 12, 21, 22, 25, 111, 226, 0].map((n) => plural(n, PRODUCTS))).toEqual([
      'товар',
      'товара',
      'товаров',
      'товаров',
      'товаров',
      'товар',
      'товара',
      'товаров',
      'товаров',
      'товаров',
      'товаров',
    ]);
  });
});
