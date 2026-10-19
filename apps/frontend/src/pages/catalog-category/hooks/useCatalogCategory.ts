import { useEffect, useLayoutEffect, useState } from 'react';
import useSWR from 'swr';
import { api, type Category } from '../../../lib/api';
import { useSSR } from '../../../contexts/ssr-context';
import {
  getCategoryFragment,
  setCategoryFragmentFromCategory,
} from '../../../lib/category-fragment-cache';

/** Категория или комната по slug: SSR → фрагментный кэш → API */
export function useCatalogCategory(slug: string | undefined, isRooms: boolean) {
  const ssrData = useSSR();
  const initialCategory: Category | null = ssrData?.category?.category || null;
  // Сервер уже получил 404 на этот раздел — повторно не спрашиваем (market-docs/55)
  const missingOnServer = Boolean(slug) && ssrData?.category?.notFoundSlug === slug;
  const [category, setCategory] = useState<Category | null>(initialCategory);

  useLayoutEffect(() => {
    if (initialCategory && initialCategory.slug === slug) setCategory(initialCategory);
  }, [initialCategory, slug]);

  // Раздел с сервера выключает ключ; пока грузится новый, на экране прежний (market-docs/54)
  const { data, error, isLoading } = useSWR(
    slug && !missingOnServer && (!initialCategory || initialCategory.slug !== slug)
      ? `/api/${isRooms ? 'rooms' : 'categories'}/${slug}`
      : null,
    () => (isRooms ? api.rooms : api.categories).get(slug!),
    { keepPreviousData: true },
  );

  useEffect(() => {
    if (!data?.category) return;
    setCategoryFragmentFromCategory(data.category);
    setCategory((prev) => (prev?.id === data.category.id ? prev : data.category));
  }, [data]);

  useEffect(() => {
    if (initialCategory && initialCategory.slug === slug) {
      setCategoryFragmentFromCategory(initialCategory);
    }
  }, [initialCategory, slug]);

  // Название и SEO: фрагментный кэш → category → ответ API → «Каталог» (slug в заголовке не показываем)
  const fragment = slug ? getCategoryFragment(slug) : null;
  const effective = category ?? data?.category ?? null;

  return {
    category,
    effectiveCategory: effective,
    displayName: fragment?.name ?? effective?.name ?? 'Каталог',
    seo: fragment?.seo ?? effective?.seo ?? null,
    notFound:
      missingOnServer ||
      Boolean(
        slug && !isLoading && !category && (error != null || (data != null && !data.category)),
      ),
  };
}
