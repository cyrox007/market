import { describe, expect, it, vi } from 'vitest';
import { retryWithoutDirectory, toProductListSearch } from './product-list-search';

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

  it('пункт справочника — directory_external_id', () => {
    expect(toProductListSearch({ page: 1, region_id: 7, directory_external_id: 'eacb5f15' })).toBe(
      'page=1&region_id=7&directory_external_id=eacb5f15',
    );
  });
});

const failure = (status: number) => Object.assign(new Error(`API Error: ${status}`), { status });

describe('справочник адресов недоступен (503)', () => {
  it('повтор без пункта, «по расстоянию» — по популярности: каталог не пустой', async () => {
    const fetch = vi
      .fn()
      .mockRejectedValueOnce(failure(503))
      .mockResolvedValueOnce({ data: [] });
    const params = {
      page: 1,
      region_id: 7,
      directory_external_id: 'eacb5f15',
      sort_by: 'distance',
      sort_order: 'asc' as const,
    };
    await expect(retryWithoutDirectory(fetch)(params)).resolves.toEqual({ data: [] });
    expect(fetch).toHaveBeenLastCalledWith({
      page: 1,
      region_id: 7,
      sort_by: 'units_sold',
      sort_order: 'desc',
    });
  });

  it('другие ошибки и запросы без пункта не повторяются', async () => {
    const other = vi.fn().mockRejectedValue(failure(500));
    await expect(
      retryWithoutDirectory(other)({ page: 1, directory_external_id: 'x' }),
    ).rejects.toMatchObject({ status: 500 });
    expect(other).toHaveBeenCalledTimes(1);

    const plain = vi.fn().mockRejectedValue(failure(503));
    await expect(retryWithoutDirectory(plain)({ page: 1 })).rejects.toMatchObject({ status: 503 });
    expect(plain).toHaveBeenCalledTimes(1);
  });
});
