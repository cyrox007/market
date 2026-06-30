import { describe, expect, it } from 'vitest';
import {
  formatStockDisplayText,
  resolveProductStockBadge,
} from './productUtils';
import type { ProductDetail, ProductVariant } from '../lib/api';

const stockSettings = {
  stock_low_max: 1,
  stock_medium_max: 5,
  stock_high_max: 10,
  show_exact_above: 0,
};

describe('formatStockDisplayText', () => {
  it('returns category labels for typical stock levels', () => {
    expect(formatStockDisplayText(0, stockSettings)).toBe('мало');
    expect(formatStockDisplayText(4, stockSettings)).toBe('средне');
    expect(formatStockDisplayText(8, stockSettings)).toBe('много');
    expect(formatStockDisplayText(123123, stockSettings)).toBe('много');
  });

  it('returns exact count only when show_exact_above is set', () => {
    const withExact = { ...stockSettings, show_exact_above: 10 };
    expect(formatStockDisplayText(11, withExact)).toBe('11 шт.');
    expect(formatStockDisplayText(10, withExact)).toBe('много');
  });
});

describe('resolveProductStockBadge', () => {
  const variant: ProductVariant = {
    id: 2,
    sku: 'V-2',
    price: 1000,
    stock: 4,
    in_stock: true,
    images: [],
    variation_attributes: [],
  };

  const heavyVariant: ProductVariant = {
    ...variant,
    id: 3,
    sku: 'V-3',
    stock: 123123,
  };

  const product = {
    id: 1,
    name: 'Parent',
    slug: 'parent',
    sku: 'P-1',
    price: 1000,
    old_price: null,
    discount_percent: null,
    image: null,
    thumbnail: null,
    in_stock: true,
    stock: 123123,
    rating: 5,
    reviews_count: 0,
    is_variable: true,
    is_variant: false,
    variants: [variant, heavyVariant],
    category: null,
    excerpt: null,
    images: [],
    description: null,
    specifications: null,
  } as ProductDetail;

  it('uses selected variant stock with category label', () => {
    const badge = resolveProductStockBadge({
      product,
      selectedVariant: variant,
      stockSettings,
    });

    expect(badge.stock).toBe(4);
    expect(badge.stockText).toBe('средне');
    expect(badge.badgeLabel).toBe('В наличии (средне)');
  });

  it('shows много for large variant stock when exact count is disabled', () => {
    const badge = resolveProductStockBadge({
      product,
      selectedVariant: heavyVariant,
      stockSettings,
    });

    expect(badge.stockText).toBe('много');
    expect(badge.badgeLabel).toBe('В наличии (много)');
  });
});
