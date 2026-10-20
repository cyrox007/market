import { describe, expect, it } from 'vitest';
import { applySeoToHead } from './seo-head';

const template = `<head>
<title>Светофор Мебели - Modern Furniture Store</title>
<meta name="description" content="Шаблон" />
<link rel="canonical" href="https://yoursite.com/" />
<meta property="og:title" content="Шаблон" />
<meta property="og:description" content="Шаблон" />
<meta property="og:url" content="https://yoursite.com/" />
</head>`;

const seo = {
  title: 'Поиск – Светофор Мебели',
  description: 'Результаты поиска',
  image: null,
  canonical_url: null,
  robots: 'noindex, follow',
  open_graph_title: null,
  locale: 'ru_RU',
};

const PAGE = 'https://svetofor.ru/search?q=диван';

describe('SEO в <head> серверной страницы', () => {
  it('заголовок, описание и og — из SEO страницы', () => {
    const head = applySeoToHead(template, seo, PAGE);
    expect(head).toContain('<title>Поиск – Светофор Мебели</title>');
    expect(head).toContain('<meta name="description" content="Результаты поиска">');
    expect(head).toContain('<meta property="og:title" content="Поиск – Светофор Мебели">');
  });

  it('robots ставится, хотя в шаблоне его нет', () => {
    expect(applySeoToHead(template, seo, PAGE)).toContain(
      '<meta name="robots" content="noindex, follow">',
    );
  });

  it('robots из шаблона заменяется, а не дублируется', () => {
    const withRobots = template.replace(
      '</head>',
      '<meta name="robots" content="index" />\n</head>',
    );
    const head = applySeoToHead(withRobots, seo, PAGE);
    expect(head.match(/name="robots"/g)).toHaveLength(1);
    expect(head).toContain('content="noindex, follow"');
  });

  it('без canonical — адрес страницы, как в браузере, а не заглушка шаблона', () => {
    const head = applySeoToHead(template, seo, PAGE);
    expect(head).not.toContain('yoursite.com');
    expect(head).toContain(`<link rel="canonical" href="${PAGE}">`);
    expect(head).toContain(`<meta property="og:url" content="${PAGE}">`);
  });

  it('canonical из SEO важнее адреса', () => {
    const head = applySeoToHead(
      template,
      { ...seo, canonical_url: 'https://svetofor.ru/catalog/divany' },
      PAGE,
    );
    expect(head).toContain('<link rel="canonical" href="https://svetofor.ru/catalog/divany">');
  });

  it('SEO нет — заглушка canonical всё равно заменяется адресом', () => {
    const head = applySeoToHead(template, null, PAGE);
    expect(head).toContain('<title>Светофор Мебели - Modern Furniture Store</title>');
    expect(head).toContain(`<link rel="canonical" href="${PAGE}">`);
  });

  it('спецсимволы экранируются', () => {
    const head = applySeoToHead(template, { ...seo, title: 'Диван "Люкс" <b>' }, PAGE);
    expect(head).toContain('<title>Диван &quot;Люкс&quot; &lt;b&gt;</title>');
  });

  it('знак $ в заголовке остаётся как есть', () => {
    expect(applySeoToHead(template, { ...seo, title: 'Скидка $& 10$' }, PAGE)).toContain(
      '<title>Скидка $&amp; 10$</title>',
    );
  });
});
