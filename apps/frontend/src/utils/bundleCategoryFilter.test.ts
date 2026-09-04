import { describe, expect, it } from 'vitest';
import type { Product } from '../lib/api';
import {
  collectBundleCategoryChips,
  filterBundleProductsByCategory,
  isBundleProductAvailable,
  sortBundleProductsWithUnavailableLast,
} from './bundleCategoryFilter';

const base = (
  overrides: Partial<Product> & { categories?: { id: number; name: string; slug: string }[] },
): Product =>
  ({
    id: 1,
    name: 'Test',
    slug: 'test',
    sku: 'sku',
    price: 100,
    old_price: null,
    discount_percent: null,
    image: null,
    thumbnail: null,
    in_stock: true,
    stock: 1,
    rating: 0,
    reviews_count: 0,
    category: null,
    excerpt: null,
    ...overrides,
  }) as Product;

describe('bundleCategoryFilter', () => {
  it('collects unique categories', () => {
    const chips = collectBundleCategoryChips([
      base({ id: 1, category: { id: 10, name: 'Диваны', slug: 'divany' } }),
      base({ id: 2, category: { id: 11, name: 'Кровати', slug: 'krovati' } }),
      base({ id: 3, category: { id: 10, name: 'Диваны', slug: 'divany' } }),
    ]);

    expect(chips).toHaveLength(2);
    expect(chips.map((c) => c.id).sort()).toEqual([10, 11]);
  });

  it('filters by selected category', () => {
    const products = [
      base({ id: 1, category: { id: 10, name: 'A', slug: 'a' } }),
      base({ id: 2, category: { id: 11, name: 'B', slug: 'b' } }),
    ];

    expect(filterBundleProductsByCategory(products, 10)).toHaveLength(1);
    expect(filterBundleProductsByCategory(products, 10)[0].id).toBe(1);
    expect(filterBundleProductsByCategory(products, null)).toHaveLength(2);
  });

  it('detects unavailable by region or stock', () => {
    expect(isBundleProductAvailable(base({ in_stock: true, is_visible_in_region: true }))).toBe(
      true,
    );
    expect(isBundleProductAvailable(base({ in_stock: false }))).toBe(false);
    expect(isBundleProductAvailable(base({ is_visible_in_region: false }))).toBe(false);
  });

  it('sorts unavailable products to the end preserving order', () => {
    const products = [
      base({ id: 1, in_stock: false }),
      base({ id: 2, in_stock: true }),
      base({ id: 3, is_visible_in_region: false }),
      base({ id: 4, in_stock: true }),
    ];
    const sorted = sortBundleProductsWithUnavailableLast(products);
    expect(sorted.map((p) => p.id)).toEqual([2, 4, 1, 3]);
  });
});
