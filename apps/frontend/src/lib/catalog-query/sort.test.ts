import { describe, expect, it } from 'vitest';
import { catalogSortOptions, searchSort, withSort } from '.';

const read = (search: string) => searchSort(new URLSearchParams(search));

describe('сортировка поиска — как в каталоге', () => {
  it('без параметра — по популярности', () => {
    expect(read('q=диван')).toEqual({ sort: 'popular', sort_by: 'units_sold', sort_order: 'desc' });
  });

  it('варианты каталога', () => {
    expect(read('q=диван&sort=rating')).toMatchObject({ sort_by: 'rating', sort_order: 'desc' });
    expect(read('q=диван&sort=cheap')).toMatchObject({ sort_by: 'price', sort_order: 'asc' });
  });

  it('старые ссылки поиска: цена — по направлению, остальное — по популярности', () => {
    expect(read('sort=price&order=desc').sort).toBe('expensive');
    expect(read('sort=created_at&order=desc').sort).toBe('popular');
    expect(read('sort=name&order=asc').sort).toBe('popular');
  });
});

describe('сортировка «Сначала ближе»', () => {
  it('в списке — только когда выбран пункт', () => {
    expect(catalogSortOptions(false).map((o) => o.value)).not.toContain('nearest');
    expect(catalogSortOptions(true).map((o) => o.value)).toContain('nearest');
  });

  it('поиск по расстоянию не сортирует — по популярности', () => {
    expect(read('q=диван&sort=nearest')).toEqual({
      sort: 'popular',
      sort_by: 'units_sold',
      sort_order: 'desc',
    });
  });
});

describe('смена сортировки в адресе поиска', () => {
  it('запрос остаётся, страница и старое направление уходят', () => {
    const next = withSort(new URLSearchParams('q=диван&sort=price&order=asc&page=3'), 'rating');
    expect(next.toString()).toBe(new URLSearchParams('q=диван&sort=rating').toString());
  });

  it('по умолчанию — без параметра, как в каталоге', () => {
    const next = withSort(new URLSearchParams('q=диван&sort=cheap'), 'popular');
    expect(next.toString()).toBe(new URLSearchParams('q=диван').toString());
  });

  it('исходный адрес не меняется', () => {
    const params = new URLSearchParams('q=диван&page=2');
    withSort(params, 'cheap');
    expect(params.toString()).toBe(new URLSearchParams('q=диван&page=2').toString());
  });
});
