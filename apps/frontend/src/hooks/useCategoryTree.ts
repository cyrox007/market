import useSWR from 'swr';
import { api } from '../lib/api';
import type { Category } from '../lib/api';
import { useSSR } from '../contexts/SSRContext';

/** Ключ один на всё приложение: шапка живёт на всех страницах, запрос должен быть один */
export const CATEGORY_TREE_KEY = '/api/categories/tree';

/**
 * Дерево категорий для шапки.
 *
 * Сервер кладёт категории в контекст только для главной, поэтому на остальных
 * маршрутах дерево грузится здесь. Подробности — market-docs/14-header-wiring.md.
 */
export function useCategoryTree() {
  const ssr = useSSR();
  const fromSSR = ssr?.home?.categories as Category[] | undefined;

  const { data, isLoading } = useSWR(CATEGORY_TREE_KEY, () => api.categories.tree(), {
    fallbackData: fromSSR?.length ? { tree: fromSSR } : undefined,
    revalidateIfStale: true,
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
  });

  return { categories: data?.tree ?? [], isLoading };
}
