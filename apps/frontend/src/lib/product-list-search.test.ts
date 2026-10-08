import { describe, expect, it } from 'vitest';
import { toProductListSearch } from './product-list-search';

describe('параметры списка товаров → строка запроса к API', () => {
  it('списки через запятую, атрибуты — attributes[slug][], пустое не передаётся', () => {
    const search = toProductListSearch({
      category_slug: 'divany',
      page: 2,
      per_page: 12,
      sort_by: 'price',
      sort_order: 'asc',
      price_min: 0,
      colors: ['grey', 'red'],
      sizes: [],
      attributes: { mechanism: ['evro', 'akk'] },
      on_sale: true,
      width_max: 3000,
      manufacturers: ['a', 'b'],
      region_id: 7,
    });
    expect(decodeURIComponent(search)).toBe(
      'category_slug=divany&price_min=0&sort_by=price&sort_order=asc&per_page=12&page=2' +
        '&colors=grey,red&attributes[mechanism][]=evro&attributes[mechanism][]=akk' +
        '&manufacturers=a,b&on_sale=1&width_max=3000&region_id=7',
    );
  });
});
