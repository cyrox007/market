import type { SeoMeta } from './api';

const escapeHtml = (s: string) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Заменить тег по шаблону; нет такого — вставить перед </head> */
function upsert(head: string, pattern: RegExp, tag: string) {
  // Функцией, а не строкой: в строке замены $& и $1 — спецсимволы
  return pattern.test(head)
    ? head.replace(pattern, () => tag)
    : head.replace('</head>', () => `${tag}</head>`);
}

const meta = (attr: 'name' | 'property', key: string) =>
  new RegExp(`<meta\\s+${attr}="${key}"\\s+content="[^"]*"\\s*\\/?>`);

/**
 * SEO страницы в <head> серверного HTML — то же, что applySeoMeta делает в браузере:
 * нет canonical — адрес страницы, а не заглушка шаблона (market-docs/55)
 */
export function applySeoToHead(
  template: string,
  seo: SeoMeta | null | undefined,
  pageUrl: string,
): string {
  let head = template;
  const canonical = seo?.canonical_url || pageUrl;
  head = upsert(
    head,
    /<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/,
    `<link rel="canonical" href="${escapeHtml(canonical)}">`,
  );
  head = upsert(
    head,
    meta('property', 'og:url'),
    `<meta property="og:url" content="${escapeHtml(canonical)}">`,
  );
  if (!seo) return head;

  if (seo.title) {
    const title = `<title>${escapeHtml(seo.title)}</title>`;
    head = head.replace(/<title>[\s\S]*?<\/title>/, () => title);
  }
  const ogTitle = seo.open_graph_title ?? seo.title;
  if (ogTitle) {
    head = upsert(
      head,
      meta('property', 'og:title'),
      `<meta property="og:title" content="${escapeHtml(ogTitle)}">`,
    );
  }
  if (seo.description) {
    const description = escapeHtml(seo.description);
    head = upsert(
      head,
      meta('name', 'description'),
      `<meta name="description" content="${description}">`,
    );
    head = upsert(
      head,
      meta('property', 'og:description'),
      `<meta property="og:description" content="${description}">`,
    );
  }
  if (seo.image) {
    head = upsert(
      head,
      meta('property', 'og:image'),
      `<meta property="og:image" content="${escapeHtml(seo.image)}">`,
    );
  }
  if (seo.robots) {
    head = upsert(
      head,
      meta('name', 'robots'),
      `<meta name="robots" content="${escapeHtml(seo.robots)}">`,
    );
  }
  return head;
}
