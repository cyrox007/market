import type { Category } from '../../lib/api';

export type CatalogPageView = 'notFound' | 'loading' | 'landing' | 'listing';

/**
 * Лендинг или список — решаем только по загруженному разделу этого адреса: до ответа API
 * список показывал «Каталог › Каталог», а у раздела с подкатегориями потом сменялся лендингом.
 */
export function pageView({
  slug,
  category,
  isRooms,
  notFound,
}: {
  slug: string | undefined;
  category: Category | null;
  isRooms: boolean;
  notFound: boolean;
}): CatalogPageView {
  if (notFound) return 'notFound';
  if (!category || category.slug !== slug) return 'loading';
  if (!isRooms && category.children?.length) return 'landing';
  return 'listing';
}
