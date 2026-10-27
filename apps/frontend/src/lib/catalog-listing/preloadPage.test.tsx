import { renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { parseCatalogQuery, type CatalogScope } from '../catalog-query';
import { useLandingProducts } from '../../pages/catalog-category/hooks/useLandingProducts';
import { preloadCatalogPage } from './preloadPage';

const api = vi.hoisted(() => ({ products: { list: vi.fn() } }));
vi.mock('../api', () => ({ api }));

describe('предзагрузка первой страницы раздела', () => {
  it('делит запрос с тем, кто уже ждёт тот же ключ, и не перезапрашивает после', async () => {
    api.products.list.mockResolvedValue({ data: [{ id: 1, name: 'Товар', slug: 't', price: 1 }] });
    // Глобальный кэш SWR — тот же, куда пишет preloadCatalogPage; slug — только для этого теста
    const scope: CatalogScope = { scope: 'category', slug: 'preload-once' };
    const { result } = renderHook(() => useLandingProducts(scope));
    preloadCatalogPage(scope, parseCatalogQuery(''));
    await waitFor(() => expect(result.current.products).toHaveLength(1));
    await new Promise((r) => setTimeout(r, 50));
    // Предзагрузка и лендинг стартуют почти одновременно — поход в API один (market-docs/62)
    expect(api.products.list).toHaveBeenCalledTimes(1);
  });
});
