import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProductListParams, ProductListResponse } from '../api';
import { fetchCatalogPage } from './fetchCatalogPage';

const list = vi.fn();
vi.mock('../api', () => ({ api: { products: { list: (p: ProductListParams) => list(p) } } }));

const params = (page = 1): ProductListParams => ({ category_slug: 'divany', page });
const answer = (id: number) => ({ data: [{ id }] }) as unknown as ProductListResponse;

beforeEach(() => list.mockReset());

describe('fetchCatalogPage', () => {
  it('два запроса одного ключа подряд — один поход в API, ответ общий', async () => {
    list.mockResolvedValue(answer(1));

    const [a, b] = await Promise.all([
      fetchCatalogPage('category:divany', params()),
      fetchCatalogPage('category:divany', params()),
    ]);

    expect(list).toHaveBeenCalledTimes(1);
    expect(a).toBe(b);
  });

  it('разные разделы и страницы не сливаются', async () => {
    list.mockResolvedValue(answer(1));

    await Promise.all([
      fetchCatalogPage('category:divany', params()),
      fetchCatalogPage('room:gostinaia', params()),
      fetchCatalogPage('category:divany', params(2)),
    ]);

    expect(list).toHaveBeenCalledTimes(3);
  });

  it('после ответа ключ освобождается — следующий запрос идёт в API', async () => {
    list.mockResolvedValue(answer(1));
    await fetchCatalogPage('category:divany', params());
    await fetchCatalogPage('category:divany', params());

    expect(list).toHaveBeenCalledTimes(2);
  });

  it('ошибка не остаётся в памяти: повтор снова идёт в API', async () => {
    list.mockRejectedValueOnce(new Error('503'));
    await expect(fetchCatalogPage('category:divany', params())).rejects.toThrow('503');

    list.mockResolvedValue(answer(2));
    await expect(fetchCatalogPage('category:divany', params())).resolves.toEqual(answer(2));
    expect(list).toHaveBeenCalledTimes(2);
  });
});
