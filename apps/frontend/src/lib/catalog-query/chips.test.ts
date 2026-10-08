import { describe, expect, it } from 'vitest';
import type { FiltersMeta } from '../api';
import {
  activeFilterCount,
  filterChips,
  parseCatalogQuery,
  removeChip,
  resetFilters,
  toCatalogSearch,
} from '.';

const meta: FiltersMeta = {
  price: { min: 5000, max: 90000 },
  colors: [{ slug: 'grey', name: 'Серый' }],
  sizes: [{ slug: '200x90', name: '200×90' }],
  attributes: [
    {
      id: 1,
      name: 'Механизм',
      slug: 'mechanism',
      values: [{ id: 2, name: 'Еврокнижка', slug: 'evro' }],
    },
  ],
  manufacturers: [{ id: 3, name: 'Шатура', slug: 'shatura' }],
};

const labels = (search: string) =>
  filterChips(parseCatalogQuery(search), { meta }).map((c) => c.label);

describe('плашки выбранных фильтров', () => {
  it('диапазон — одна плашка: «от–до», «от», «до»', () => {
    expect(labels('?price_min=10000&price_max=50000')).toEqual(['Цена 10 000–50 000 ₽']);
    expect(labels('?width_min=2400')).toEqual(['Ширина от 2 400 мм']);
    expect(labels('?height_max=900')).toEqual(['Высота до 900 мм']);
  });

  it('значения списков — по плашке на каждое, с названием из meta', () => {
    expect(
      labels('?manufacturers=shatura,ikea&colors=grey&sizes=200x90&attr_mechanism=evro&on_sale=1'),
    ).toEqual(['По акции', 'Шатура', 'ikea', 'Серый', '200×90', 'Еврокнижка']);
  });

  it('подкатегории подписываются из переданного списка', () => {
    const chips = filterChips(parseCatalogQuery('?category=uglovye'), {
      categories: [{ slug: 'uglovye', name: 'Угловые' }],
    });
    expect(chips.map((c) => c.label)).toEqual(['Угловые']);
  });

  it('сортировка и страница — не плашки', () => {
    expect(labels('?sort=cheap&page=3')).toEqual([]);
  });
});

describe('снятие плашки и число фильтров', () => {
  it('снятие плашки убирает только её и возвращает на первую страницу', () => {
    const query = parseCatalogQuery('?price_min=10000&colors=grey,red&page=4&sort=cheap');
    const red = filterChips(query, { meta }).find((c) => c.label === 'red')!;
    expect(toCatalogSearch(removeChip(query, red.id))).toBe(
      'sort=cheap&price_min=10000&colors=grey',
    );

    const price = filterChips(query, { meta }).find((c) => c.label.startsWith('Цена'))!;
    expect(toCatalogSearch(removeChip(query, price.id))).toBe('sort=cheap&colors=grey%2Cred');
  });

  it('последнее значение атрибута убирает атрибут целиком', () => {
    const query = parseCatalogQuery('?attr_mechanism=evro');
    const [chip] = filterChips(query, { meta });
    expect(removeChip(query, chip.id).attributes).toEqual({});
  });

  it('число на «Фильтры (N)» равно числу плашек', () => {
    const search = '?price_min=1&width_max=2&manufacturers=a,b&attr_x=y&on_sale=1&sort=cheap';
    expect(activeFilterCount(parseCatalogQuery(search))).toBe(6);
    expect(activeFilterCount(parseCatalogQuery(''))).toBe(0);
  });
});

describe('сброс фильтров', () => {
  it('снимает все фильтры, сохраняет сортировку, возвращает на первую страницу', () => {
    const query = parseCatalogQuery('?sort=cheap&price_min=1&colors=a&attr_x=y&on_sale=1&page=3');
    expect(toCatalogSearch(resetFilters(query))).toBe('sort=cheap');
  });
});
