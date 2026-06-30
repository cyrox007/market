import { useEffect } from 'react';
import type { SeoMeta } from '../lib/api';
import { applySeoMeta } from '../utils/seo';

/**
 * Хук: при монтировании и при смене seo обновляет document.title и meta-теги.
 * Вызывать на страницах, где есть данные с SEO (товар, категория, about и т.д.).
 */
export function usePageSeo(seo: SeoMeta | null | undefined): void {
  useEffect(() => {
    applySeoMeta(seo);
  }, [seo?.title, seo?.description, seo?.image, seo?.canonical_url, seo?.robots, seo?.open_graph_title]);
}
