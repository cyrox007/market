import { SWRConfiguration } from 'swr';
import { swrFetcher } from './swr-fetcher';
import { ssrToSwrFallback } from '../utils/ssr-to-swr';

/** Кэш из SSR: при гидрации данные уже есть, не дергаем API каждые 1.5 сек */
function getSSRFallback(): Record<string, any> {
  if (typeof window === 'undefined') return {};

  const initialState = (window as any).__INITIAL_STATE__;
  if (!initialState) return {};

  const fallback = ssrToSwrFallback(initialState);

  if (initialState.home) {
    if (initialState.home.newProducts) {
      fallback['/api/products/new'] = { data: initialState.home.newProducts };
    }
    if (initialState.home.featuredProducts) {
      fallback['/api/products/featured'] = { data: initialState.home.featuredProducts };
    }
    if (initialState.home.saleProducts) {
      fallback['/api/products/sale'] = { data: initialState.home.saleProducts };
    }
  }

  return fallback;
}

export const swrConfig: SWRConfiguration = {
  fetcher: swrFetcher,
  fallback: typeof window !== 'undefined' ? getSSRFallback() : {},
  revalidateOnFocus: false,
  revalidateOnReconnect: true,
  revalidateIfStale: true,
  revalidateInterval: 60_000, // 1 мин — было 1.5 сек, из‑за этого страницы «подлагивали»
  dedupingInterval: 15_000,  // 15 сек дедуп — не дергать один и тот же ключ при переходах
  focusThrottleInterval: 10_000,
  errorRetryCount: 2,
  errorRetryInterval: 8000,
  shouldRetryOnError: (error: any) => {
    if (error?.status >= 400 && error?.status < 500) {
      return error?.status === 408 || error?.status === 429;
    }
    return true;
  },
};

export function createSwrConfig(customFallback?: Record<string, any>): SWRConfiguration {
  const ssrFallback = getSSRFallback();
  return {
    ...swrConfig,
    fallback: {
      ...ssrFallback,
      ...customFallback,
    },
  };
}
