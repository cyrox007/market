import { describe, expect, it } from 'vitest';
import {
  getHomeProductsKey,
  getProductKey,
  getSearchKey,
  ssrToSwrFallback,
} from './ssr-to-swr';

describe('ssr-to-swr utilities', () => {
  it('builds home key with region', () => {
    expect(getHomeProductsKey('featured', 77)).toBe('/api/products/featured?region_id=77');
    expect(getHomeProductsKey('new')).toBe('/api/products/new');
  });

  it('пункт справочника — в ключах главной и карточки', () => {
    expect(getHomeProductsKey('featured', 77, 'eacb5f15')).toBe(
      '/api/products/featured?region_id=77&directory_external_id=eacb5f15',
    );
    expect(getProductKey('divan', { region_id: 7, directory_external_id: 'eacb5f15' })).toBe(
      '/api/products/divan?region_id=7&directory_external_id=eacb5f15',
    );
  });

  it('builds search key with sort and region', () => {
    const key = getSearchKey('диван', {
      page: 3,
      sort_by: 'price',
      sort_order: 'desc',
      region_id: 9,
    });
    expect(key).toContain('q=%D0%B4%D0%B8%D0%B2%D0%B0%D0%BD');
    expect(key).toContain('sort_by=price');
    expect(key).toContain('sort_order=desc');
    expect(key).toContain('region_id=9');
  });

  it('hydrates regional home fallback keys', () => {
    const fallback = ssrToSwrFallback({
      region: { id: 2 } as any,
      home: {
        featuredProducts: [{ id: 1 }],
        newProducts: [{ id: 2 }],
        saleProducts: [{ id: 3 }],
      } as any,
    } as any);

    expect(fallback['/api/products/featured?region_id=2']).toBeTruthy();
    expect(fallback['/api/products/new?region_id=2']).toBeTruthy();
    expect(fallback['/api/products/sale?region_id=2']).toBeTruthy();
  });
});
