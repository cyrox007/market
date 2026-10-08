import { describe, expect, it } from 'vitest';
import { CATALOG_PER_PAGE, parseCatalogQuery, toApiParams } from '.';

const category = { scope: 'category', slug: 'divany-pryamye' } as const;

describe('запрос каталога → параметры API', () => {
  it('без фильтров — категория, страница, по популярности', () => {
    expect(toApiParams(parseCatalogQuery(''), { ...category, regionId: 7 })).toEqual({
      category_slug: 'divany-pryamye',
      page: 1,
      per_page: CATALOG_PER_PAGE,
      sort_by: 'units_sold',
      sort_order: 'desc',
      region_id: 7,
    });
  });

  it('комната передаётся как room_slug', () => {
    const params = toApiParams(parseCatalogQuery(''), { scope: 'room', slug: 'gostinaya' });
    expect(params).toMatchObject({ room_slug: 'gostinaya' });
    expect(params).not.toHaveProperty('category_slug');
    expect(params).not.toHaveProperty('region_id');
  });

  it('сортировка по цене', () => {
    expect(toApiParams(parseCatalogQuery('?sort=cheap'), category)).toMatchObject({
      sort_by: 'price',
      sort_order: 'asc',
    });
    expect(toApiParams(parseCatalogQuery('?sort=expensive'), category)).toMatchObject({
      sort_by: 'price',
      sort_order: 'desc',
    });
  });

  it('фильтры передаются, пустые не передаются', () => {
    const query = parseCatalogQuery(
      '?page=2&price_min=10000&width_max=3000&colors=grey&sizes=200x90' +
        '&attr_mechanism=evro&on_sale=1',
    );
    expect(toApiParams(query, category)).toEqual({
      category_slug: 'divany-pryamye',
      page: 2,
      per_page: CATALOG_PER_PAGE,
      sort_by: 'units_sold',
      sort_order: 'desc',
      price_min: 10000,
      width_max: 3000,
      colors: ['grey'],
      sizes: ['200x90'],
      attributes: { mechanism: ['evro'] },
      on_sale: true,
    });
  });

  it('один производитель — manufacturer_slug, несколько — manufacturers', () => {
    expect(toApiParams(parseCatalogQuery('?manufacturers=ikea'), category)).toMatchObject({
      manufacturer_slug: 'ikea',
    });
    const several = toApiParams(parseCatalogQuery('?manufacturers=ikea,shatura'), category);
    expect(several).toMatchObject({ manufacturers: ['ikea', 'shatura'] });
    expect(several).not.toHaveProperty('manufacturer_slug');
  });

  it('одна подкатегория заменяет категорию, несколько — category_slugs', () => {
    expect(toApiParams(parseCatalogQuery('?category=uglovye'), category)).toMatchObject({
      category_slug: 'uglovye',
    });
    expect(toApiParams(parseCatalogQuery('?category=a,b'), category)).toMatchObject({
      category_slug: 'divany-pryamye',
      category_slugs: ['a', 'b'],
    });
  });
});
