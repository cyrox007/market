import { describe, expect, it } from 'vitest';
import { parseCatalogQuery, toCatalogSearch } from '.';

describe('адрес ↔ запрос каталога', () => {
  it('пустой адрес — первая страница, по популярности, без фильтров', () => {
    const query = parseCatalogQuery('');
    expect(query.page).toBe(1);
    expect(query.sort).toBe('popular');
    expect(toCatalogSearch(query)).toBe('');
  });

  it('сортировка и страница сохраняются туда и обратно', () => {
    const query = parseCatalogQuery('?sort=cheap&page=3');
    expect(query).toMatchObject({ sort: 'cheap', page: 3 });
    expect(toCatalogSearch(query)).toBe('sort=cheap&page=3');
  });

  it('неизвестная сортировка и кривая страница заменяются значениями по умолчанию', () => {
    expect(parseCatalogQuery('?sort=lol&page=-2')).toMatchObject({ sort: 'popular', page: 1 });
    expect(parseCatalogQuery('?page=abc').page).toBe(1);
  });

  it('старые ссылки sort+order читаются и переписываются в новом виде', () => {
    expect(toCatalogSearch(parseCatalogQuery('?sort=price&order=asc'))).toBe('sort=cheap');
    expect(toCatalogSearch(parseCatalogQuery('?sort=price&order=desc'))).toBe('sort=expensive');
    expect(toCatalogSearch(parseCatalogQuery('?sort=created_at&order=desc'))).toBe('');
  });

  it('диапазоны цены и габаритов сохраняются туда и обратно', () => {
    const search = 'price_min=10000&price_max=50000&width_min=2400&height_max=900';
    const query = parseCatalogQuery(search);
    expect(query.price).toEqual({ min: 10000, max: 50000 });
    expect(query.width).toEqual({ min: 2400 });
    expect(query.depth).toEqual({});
    expect(query.height).toEqual({ max: 900 });
    expect(toCatalogSearch(query)).toBe(search);
  });

  it('мусор в диапазоне отбрасывается, «от» больше «до» — меняются местами', () => {
    expect(parseCatalogQuery('?price_min=abc&price_max=-5').price).toEqual({});
    expect(parseCatalogQuery('?width_min=3000&width_max=2400').width).toEqual({
      min: 2400,
      max: 3000,
    });
  });

  it('списки, атрибуты и «По акции» сохраняются туда и обратно', () => {
    const search =
      'manufacturers=ikea%2Cshatura&category=pryamye&colors=grey&sizes=200x90' +
      '&attr_mechanism=evro%2Cakkordeon&on_sale=1';
    const query = parseCatalogQuery(search);
    expect(query).toMatchObject({
      manufacturers: ['ikea', 'shatura'],
      categories: ['pryamye'],
      colors: ['grey'],
      sizes: ['200x90'],
      attributes: { mechanism: ['evro', 'akkordeon'] },
      onSale: true,
    });
    expect(toCatalogSearch(query)).toBe(search);
  });

  it('повторы и пустые значения в списках отбрасываются', () => {
    const query = parseCatalogQuery('?colors=grey,,grey&colors=red&attr_mechanism=&on_sale=0');
    expect(query.colors).toEqual(['grey', 'red']);
    expect(query.attributes).toEqual({});
    expect(query.onSale).toBe(false);
  });
});
