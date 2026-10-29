import useSWR from 'swr';
import { api, type Review } from '../../../lib/api';
import { useSSR } from '../../../contexts/ssr-context';

export type ReviewsStatus = 'ready' | 'loading' | 'error';

const NONE: Review[] = [];

/** Одобренные отзывы товара, весь список; с сервера — в HTML (market-docs/57-задачи/10) */
export function useProductReviews(productId: number, count: number) {
  const ssr = useSSR()?.productReviews;
  const fallback = ssr && ssr.productId === productId ? { data: ssr.data } : undefined;

  const { data, error, mutate } = useSWR(
    count > 0 ? `/products/${productId}/reviews` : null,
    () => api.reviews.list(productId),
    {
      fallbackData: fallback,
      revalidateOnMount: !fallback,
      revalidateOnFocus: false,
      dedupingInterval: 60_000,
    },
  );

  const reviews = data?.data ?? NONE;
  const status: ReviewsStatus = count === 0 || data ? 'ready' : error ? 'error' : 'loading';
  return { reviews, status, retry: () => mutate() };
}
