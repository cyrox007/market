import type { SeoMeta } from './api';

export interface CategoryFragment {
  name: string;
  seo: SeoMeta | null;
}

const cache = new Map<string, CategoryFragment>();

/**
 * Фрагментный кэш: slug → { name, seo }.
 * При переходе в категорию сразу подставляем название и title из кэша, пока не подгрузились полные данные.
 */
export function getCategoryFragment(slug: string): CategoryFragment | null {
  if (!slug) return null;
  return cache.get(slug) ?? null;
}

export function setCategoryFragment(slug: string, fragment: CategoryFragment): void {
  if (!slug) return;
  cache.set(slug, fragment);
}

/** Заполнить кэш из объекта категории (после загрузки с API или SSR). */
export function setCategoryFragmentFromCategory(category: {
  slug: string;
  name: string;
  seo?: SeoMeta | null;
}): void {
  setCategoryFragment(category.slug, {
    name: category.name,
    seo: category.seo ?? null,
  });
}
