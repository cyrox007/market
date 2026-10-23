import { useEffect, useState } from 'react';
import useSWR from 'swr';
import { api } from '../../../lib/api';
import type { ProductPagePayload } from '../../../lib/api';
import { useSSR } from '../../../contexts/ssr-context';
import { useLocationParams } from '../../../hooks/useLocationParams';
import { getProductKey } from '../../../utils/ssr-to-swr';

export type ProductPageStatus = 'ready' | 'loading' | 'not-found' | 'error';

/** Товар по адресу: с сервера, если он прислал этот же товар, иначе из API; при смене региона — перезапрос */
export function useProductPage(slug: string | undefined) {
  const ssr = useSSR()?.product;
  const { params, ready } = useLocationParams();

  const ssrPayload: ProductPagePayload | undefined =
    ssr?.product && ssr.product.slug === slug
      ? {
          product: ssr.product,
          variant_info: ssr.variant_info,
          bundle: ssr.bundle,
          related: ssr.related,
        }
      : undefined;

  // Без SSR ждём сохранённый пункт, иначе первый запрос уйдёт без него (market-docs/44)
  const key = slug && (ready || ssrPayload) ? getProductKey(slug, params) : null;
  const { data, error, isLoading, mutate } = useSWR(key, () => api.products.get(slug!, params), {
    fallbackData: ssrPayload,
    // SSR пункт не знает (он в localStorage): с пунктом ответ сервера перезапрашиваем
    revalidateOnMount: !ssrPayload || Boolean(params.directory_external_id),
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
    keepPreviousData: true,
  });

  const [isRefreshing, setIsRefreshing] = useState(false);
  useEffect(() => {
    if (!slug) return;
    const reload = () => {
      setIsRefreshing(true);
      mutate()
        .catch((err) => console.error('Failed to reload product:', err))
        .finally(() => setIsRefreshing(false));
    };
    window.addEventListener('region-changed', reload);
    return () => window.removeEventListener('region-changed', reload);
  }, [slug, mutate]);

  // keepPreviousData держит прошлый товар, пока грузится новый, — его не показываем
  const product = data?.product && data.product.slug === slug ? data.product : null;

  let status: ProductPageStatus;
  if (product) status = 'ready';
  else if (error) status = error.status === 404 ? 'not-found' : 'error';
  else if (isLoading || data === undefined || (data.product && data.product.slug !== slug))
    status = 'loading';
  else status = 'not-found';

  const related = Array.isArray(data?.related?.data) && product ? data.related.data : [];

  return { product, status, related, isRefreshing, retry: () => mutate() };
}
