import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { usePageSeo } from './usePageSeo';
import type { SeoMeta } from '../lib/api';

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
    });
    expect(document.title).toBe('Диваны');

    rerender({ seo: seoFor('Кровати') });
    expect(document.title).toBe('Кровати');
  });

  it('не переприменяет seo, когда объект пересоздан с тем же содержимым', () => {
    // Страницы собирают объект прямо в рендере: он новый на каждой перерисовке.
    // Если хук зависит от объекта, а не от полей, он перетрёт заголовок, выставленный кем-то ещё.
    const { rerender } = renderHook(({ seo }) => usePageSeo(seo), {
      initialProps: { seo: seoFor('Диваны') },
    });
    expect(document.title).toBe('Диваны');

    document.title = 'Выставлено другим кодом';
    rerender({ seo: seoFor('Диваны') });

    expect(document.title).toBe('Выставлено другим кодом');
  });
});
