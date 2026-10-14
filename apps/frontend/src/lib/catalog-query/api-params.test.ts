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

  it('сортировка по рейтингу — от высокого (market-docs/44, бэкенд 14.10)', () => {
    expect(toApiParams(parseCatalogQuery('?sort=rating'), category)).toMatchObject({
      sort_by: 'rating',
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

  it('производители — характеристикой proizvoditel, рядом с другими (market-docs/44, ответ 3)', () => {
    const params = toApiParams(
      parseCatalogQuery('?manufacturers=ikea,shatura&attr_mechanism=evro'),
      category,
    );
    expect(params.attributes).toEqual({ mechanism: ['evro'], proizvoditel: ['ikea', 'shatura'] });
    expect(params).not.toHaveProperty('manufacturer_slug');
    expect(params).not.toHaveProperty('manufacturers');
  });

  it('attr_proizvoditel в адресе читается как производители', () => {
    expect(parseCatalogQuery('?attr_proizvoditel=ikea').manufacturers).toEqual(['ikea']);
    expect(parseCatalogQuery('?attr_proizvoditel=ikea').attributes).toEqual({});
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
