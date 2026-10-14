import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { FiltersMeta } from '../../../../lib/api';
import { parseCatalogQuery, type CatalogQuery } from '../../../../lib/catalog-query';
import FilterChips from './FilterChips';

const meta: FiltersMeta = {
  price: { min: 890, max: 180350 },
  colors: [],
  sizes: [],
  attributes: [],
  manufacturers: [{ id: 3, name: 'Шатура', slug: 'shatura' }],
};

function renderChips(search: string) {
  const onQueryChange = vi.fn<(next: CatalogQuery) => void>();
  const view = render(
    <FilterChips
      query={parseCatalogQuery(search)}
      meta={meta}
      subcategories={[{ slug: 'uglovye', name: 'Диваны угловые' }]}
      onQueryChange={onQueryChange}
    />,
  );
  return { onQueryChange, ...view };
}

describe('плашки выбранных фильтров', () => {
  it('крестик снимает фильтр сразу, без «Показать N»', () => {
    const { onQueryChange } = renderChips('?sort=cheap&price_min=10000&manufacturers=shatura');

    fireEvent.click(screen.getByRole('button', { name: 'Убрать фильтр «Шатура»' }));

    expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?sort=cheap&price_min=10000'));
  });

  it('«Сбросить всё» снимает все фильтры, сортировку оставляет', () => {
    const { onQueryChange } = renderChips('?sort=cheap&page=2&price_min=10000&on_sale=1');

    fireEvent.click(screen.getByRole('button', { name: 'Сбросить всё' }));

    expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?sort=cheap'));
  });

  it('без выбранных фильтров ничего не показывается — сортировка не плашка', () => {
    renderChips('?sort=cheap&page=2');

    expect(screen.queryAllByRole('button')).toHaveLength(0);
  });

  it('снят последний фильтр — строка сворачивается плавно: плашка ещё на месте, но скрыта', () => {
    const { rerender } = renderChips('?on_sale=1');
    expect(screen.getByRole('button', { name: 'Сбросить всё' })).toBeVisible();

    rerender(
      <FilterChips query={parseCatalogQuery('')} meta={meta} onQueryChange={vi.fn()} />,
    );

    expect(screen.getByText('По акции')).not.toBeVisible();
    expect(screen.queryByRole('button', { name: 'Сбросить всё' })).not.toBeInTheDocument();
  });

  it('подкатегория подписана названием', () => {
    renderChips('?category=uglovye');

    expect(screen.getByRole('button', { name: 'Убрать фильтр «Диваны угловые»' })).toBeVisible();
  });
});
