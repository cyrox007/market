import { useMemo } from 'react';
import useSWR from 'swr';
import { api, type ProductListParams } from '../../../lib/api';
import { normalizeListProduct } from '../../../utils/cartProduct';

const LANDING_PRODUCTS = 4;

/** Популярные товары для лендинга родительской категории */
export function useLandingProducts(slug: string | null, regionId?: number) {
  const params: ProductListParams | null = slug
    ? {
        category_slug: slug,
        page: 1,
        per_page: LANDING_PRODUCTS,
        sort_by: 'units_sold',
        sort_order: 'desc',
        ...(regionId && { region_id: regionId }),
      }
    : null;

  const { data, isLoading } = useSWR(
    params ? (['landing-products', params] as const) : null,
    ([, p]) => api.products.list(p),
    { revalidateOnFocus: false },
  );

  const products = useMemo(
    () =>
      (data?.data ?? [])
        .filter((p) => p.is_visible_in_region !== false)
        .map((p) => normalizeListProduct(p)),
    [data],
  );

  return { products, isLoading: isLoading && !data };
}
