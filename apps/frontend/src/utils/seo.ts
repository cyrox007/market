import type { SeoMeta } from '../lib/api';

const META_NAMES = ['description', 'robots'] as const;
const OG_PROPERTIES = ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'] as const;

/**
 * Устанавливает в document заголовок и meta-теги из объекта SEO с бэкенда.
 * Используется на страницах товара, категории, «О нас» и т.д.
 */
export function applySeoMeta(seo: SeoMeta | null | undefined): void {
  if (!seo) return;

  if (seo.title) {
    document.title = seo.title || 'Светофор Мебели';
  }

  // description
  setMetaTag('name', 'description', seo.description);

  // robots
  setMetaTag('name', 'robots', seo.robots ?? undefined);

  // Open Graph
  setMetaTag('property', 'og:title', seo.open_graph_title ?? seo.title ?? undefined);
  setMetaTag('property', 'og:description', seo.description ?? undefined);
  setMetaTag('property', 'og:image', seo.image ?? undefined);
  setMetaTag(
    'property',
    'og:url',
    seo.canonical_url ?? (typeof window !== 'undefined' ? window.location.href : undefined),
  );
  setMetaTag('property', 'og:type', 'website');

  // canonical
  let link = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (seo.canonical_url) {
    if (!link) {
      link = document.createElement('link');
      link.rel = 'canonical';
      document.head.appendChild(link);
    }
    link.href = seo.canonical_url;
  } else if (link) {
    link.remove();
  }
}

function setMetaTag(attr: 'name' | 'property', key: string, value: string | undefined): void {
  let el = document.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`);
  if (value) {
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute(attr, key);
      document.head.appendChild(el);
    }
    el.content = value;
  } else if (el) {
    el.remove();
  }
}
