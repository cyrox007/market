import { useEffect } from 'react';
import type { SeoMeta } from '../lib/api';
import { applySeoMeta } from '../utils/seo';

/**
 * Обновляет document.title и meta-теги, когда меняется содержимое seo.
 *
 * Зависит от полей, а не от объекта: страницы собирают seo прямо в рендере,
 * и зависимость от объекта переприменяла бы теги на каждой перерисовке.
 * Поведение закреплено тестом рядом.
 */
export function usePageSeo(seo: SeoMeta | null | undefined): void {
  const hasSeo = seo != null;
  const title = seo?.title ?? null;
  const description = seo?.description ?? null;
  const image = seo?.image ?? null;
  const canonicalUrl = seo?.canonical_url;
  const robots = seo?.robots ?? null;
  const openGraphTitle = seo?.open_graph_title ?? null;
  const locale = seo?.locale ?? null;

  useEffect(() => {
    if (!hasSeo) return;
    applySeoMeta({
      title,
      description,
      image,
      canonical_url: canonicalUrl,
      robots,
      open_graph_title: openGraphTitle,
      locale,
    });
  }, [hasSeo, title, description, image, canonicalUrl, robots, openGraphTitle, locale]);
}
