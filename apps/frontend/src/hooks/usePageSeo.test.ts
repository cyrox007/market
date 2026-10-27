import type { ReactNode } from 'react';
import { createElement } from 'react';
import { renderHook } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import { usePageSeo } from './usePageSeo';
import type { SeoMeta } from '../lib/api';

/** Хук читает номер страницы из адреса, поэтому живёт внутри роутера */
const routerAt = (url: string) =>
  function Wrapper({ children }: { children: ReactNode }) {
    return createElement(MemoryRouter, { initialEntries: [url] }, children);
  };

const seoFor = (title: string): SeoMeta => ({
  title,
  description: `${title} — описание`,
  image: null,
  canonical_url: `https://example.test/${title}`,
  robots: 'index, follow',
  open_graph_title: title,
  locale: 'ru_RU',
});

describe('usePageSeo', () => {
  afterEach(() => {
    document.title = '';
  });

  it('обновляет заголовок страницы, когда меняется содержимое seo', () => {
    const { rerender } = renderHook(({ seo }) => usePageSeo(seo), {
      initialProps: { seo: seoFor('Диваны') },
      wrapper: routerAt('/catalog/divany'),
    });
    expect(document.title).toBe('Диваны');

    rerender({ seo: seoFor('Кровати') });
    expect(document.title).toBe('Кровати');
  });

  const canonical = () =>
    document.querySelector<HTMLLinkElement>('link[rel="canonical"]')?.getAttribute('href');

  it('первая страница — canonical раздела как есть', () => {
    renderHook(() => usePageSeo(seoFor('Диваны')), { wrapper: routerAt('/catalog/divany') });
    expect(canonical()).toBe('https://example.test/Диваны');
  });

  it('вторая и дальше — canonical на себя, фильтры и сортировка в него не идут', () => {
    renderHook(() => usePageSeo(seoFor('Диваны')), {
      wrapper: routerAt('/catalog/divany?colors=seryi&sort=cheap&page=3'),
    });
    expect(canonical()).toBe('https://example.test/Диваны?page=3');
  });

  it('не переприменяет seo, когда объект пересоздан с тем же содержимым', () => {
    // Страницы собирают объект прямо в рендере: он новый на каждой перерисовке.
    // Если хук зависит от объекта, а не от полей, он перетрёт заголовок, выставленный кем-то ещё.
    const { rerender } = renderHook(({ seo }) => usePageSeo(seo), {
      initialProps: { seo: seoFor('Диваны') },
      wrapper: routerAt('/catalog/divany'),
    });
    expect(document.title).toBe('Диваны');

    document.title = 'Выставлено другим кодом';
    rerender({ seo: seoFor('Диваны') });

    expect(document.title).toBe('Выставлено другим кодом');
  });
});
