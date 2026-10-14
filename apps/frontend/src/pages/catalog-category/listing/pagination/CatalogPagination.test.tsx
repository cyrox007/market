import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { parseCatalogQuery } from '../../../../lib/catalog-query';
import CatalogPagination from './CatalogPagination';

function setup(props: Partial<Parameters<typeof CatalogPagination>[0]> = {}) {
  const onPage = vi.fn();
  const onLoadMore = vi.fn();
  render(
    <MemoryRouter initialEntries={['/catalog/divany?sort=cheap&page=2']}>
      <CatalogPagination
        query={parseCatalogQuery('sort=cheap&page=2')}
        pages={{ from: 1, to: 2 }}
        lastPage={16}
        hasMore
        isLoadingMore={false}
        onLoadMore={onLoadMore}
        onPage={onPage}
        {...props}
      />
    </MemoryRouter>,
  );
  return { onPage, onLoadMore };
}

describe('страницы списка', () => {
  it('номер — ссылка с фильтрами для поисковика, клик открывает страницу без перехода', () => {
    const { onPage } = setup();
    const link = screen.getByRole('link', { name: 'Страница 16' });
    expect(link).toHaveAttribute('href', '/catalog/divany?sort=cheap&page=16');
    fireEvent.click(link);
    expect(onPage).toHaveBeenCalledWith(16);
  });

  it('после «Показать ещё» отмечены все показанные страницы', () => {
    setup();
    expect(screen.getByRole('link', { name: 'Страница 1' })).toHaveAttribute(
      'aria-current',
      'page',
    );
    expect(screen.getByRole('link', { name: 'Страница 2' })).toHaveAttribute(
      'aria-current',
      'page',
    );
    expect(screen.getByRole('link', { name: 'Страница 3' })).not.toHaveAttribute('aria-current');
  });

  it('«Показать ещё» дописывает следующую', () => {
    const { onLoadMore } = setup();
    fireEvent.click(screen.getByRole('button', { name: 'Показать ещё' }));
    expect(onLoadMore).toHaveBeenCalled();
  });

  it('на последней странице «Показать ещё» нет', () => {
    setup({ pages: { from: 16, to: 16 }, hasMore: false });
    expect(screen.queryByRole('button', { name: 'Показать ещё' })).not.toBeInTheDocument();
  });

  it('одна страница — ни кнопки, ни номеров', () => {
    const { container } = render(
      <MemoryRouter>
        <CatalogPagination
          query={parseCatalogQuery('')}
          pages={{ from: 1, to: 1 }}
          lastPage={1}
          hasMore={false}
          isLoadingMore={false}
          onLoadMore={vi.fn()}
          onPage={vi.fn()}
        />
      </MemoryRouter>,
    );
    expect(container).toBeEmptyDOMElement();
  });
});
