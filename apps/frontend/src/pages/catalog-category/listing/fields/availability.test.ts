import { describe, expect, it } from 'vitest';
import type { FiltersMeta } from '../../../../lib/api';
import { parseCatalogQuery } from '../../../../lib/catalog-query';
import { filterAvailability } from './availability';

const meta = (patch: Partial<FiltersMeta> = {}): FiltersMeta => ({
  price: { min: 10000, max: 90000 },
  colors: [],
  sizes: [],
  attributes: [],
  ...patch,
});

const none = parseCatalogQuery('');

describe('какие фильтры показать', () => {
  it('пока meta нет — ничего', () => {
    expect(filterAvailability(null, none)).toEqual({
      price: null,
      dimensions: [],
      manufacturers: null,
      onSale: false,
    });
  });

  it('цена — по границам раздела; одна цена на всех — выбирать нечего', () => {
    expect(filterAvailability(meta(), none).price).toEqual({ min: 10000, max: 90000 });
    expect(filterAvailability(meta({ price: { min: 5000, max: 5000 } }), none).price).toBeNull();
  });

  it('выбранное за краем раздела расширяет границы, иначе его не снять', () => {
    const query = parseCatalogQuery('?price_min=2000&width_max=4000');
    const available = filterAvailability(
      meta({ dimensions: { width: { min: 1200, max: 2400 } } }),
      query,
    );
    expect(available.price).toEqual({ min: 2000, max: 90000 });
    expect(available.dimensions).toEqual([
      { key: 'width', title: 'Ширина', bounds: { min: 1200, max: 4000 } },
    ]);
  });

  it('габарит без данных (null) не показываем, пока его не выбрали', () => {
    const withNull = meta({
      dimensions: { width: { min: null, max: null }, height: { min: 700, max: 950 } },
    });
    expect(filterAvailability(withNull, none).dimensions.map((d) => d.key)).toEqual(['height']);
    expect(
      filterAvailability(withNull, parseCatalogQuery('?width_min=1000')).dimensions[0],
    ).toMatchObject({ key: 'width', bounds: { min: 1000, max: 1000 } });
  });

  it('производители — из характеристики, иначе из manufacturers; пусто — нет фильтра', () => {
    const attr = meta({
      attributes: [
        {
          id: 1,
          name: 'Производитель',
          slug: 'proizvoditel',
          values: [{ id: 1, name: 'Мебель-М', slug: 'mebel-m' }],
        },
      ],
      manufacturers: [{ id: 9, name: 'Старый', slug: 'old' }],
    });
    expect(filterAvailability(attr, none).manufacturers?.map((m) => m.slug)).toEqual(['mebel-m']);
    expect(
      filterAvailability(meta({ manufacturers: [{ id: 9, name: 'Старый', slug: 'old' }] }), none)
        .manufacturers,
    ).toHaveLength(1);
    expect(filterAvailability(meta(), none).manufacturers).toBeNull();
    expect(filterAvailability(meta(), parseCatalogQuery('?manufacturers=x')).manufacturers).toEqual(
      [],
    );
  });

  it('«По акции» — если есть товары по акции или уже выбрано', () => {
    expect(filterAvailability(meta({ has_sale: true }), none).onSale).toBe(true);
    expect(filterAvailability(meta(), none).onSale).toBe(false);
    expect(filterAvailability(meta(), parseCatalogQuery('?on_sale=1')).onSale).toBe(true);
  });
});
