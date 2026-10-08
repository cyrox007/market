import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FiltersMeta } from '../../../../lib/api';
import { parseCatalogQuery, type CatalogQuery } from '../../../../lib/catalog-query';
import AllFiltersPanel from './AllFiltersPanel';

let compact = false;

beforeAll(() => {
  window.matchMedia = ((query: string) => ({
    matches: compact && query.includes('979.98'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

beforeEach(() => {
  compact = false;
  vi.useFakeTimers();
});
afterEach(() => vi.useRealTimers());

const meta: FiltersMeta = {
  price: { min: 890, max: 180350 },
  colors: [{ slug: 'grey', name: 'Серый', count: 3 }],
  sizes: [],
  attributes: [
    {
      id: 1,
      name: 'Механизм трансформации',
      slug: 'mechanism',
      values: [
        { id: 2, name: 'Еврокнижка', slug: 'evro', count: 5 },
        { id: 3, name: 'Аккордеон', slug: 'akk', count: 0 },
      ],
    },
  ],
  manufacturers: [
    { id: 1, name: 'Мебельный Рай', slug: 'rai' },
    { id: 2, name: 'АСМ', slug: 'asm' },
  ],
  has_sale: true,
};

function renderPanel(search = '', options: { count?: number } = {}) {
  const onQueryChange = vi.fn<(next: CatalogQuery) => void>();
  const onClose = vi.fn();
  const countProducts = vi.fn(() => Promise.resolve(options.count ?? 42));
  render(
    <AllFiltersPanel
      open
      compact={compact}
      query={parseCatalogQuery(search)}
      onQueryChange={onQueryChange}
      onClose={onClose}
      meta={meta}
      total={226}
      countProducts={countProducts}
      subcategories={[{ slug: 'uglovye', name: 'Диваны угловые' }]}
    />,
  );
  return { onQueryChange, onClose, countProducts, dialog: screen.getByRole('dialog') };
}

describe('панель «Все фильтры»', () => {
  it('разделы по макету; механизм — свёрнут', () => {
    const { dialog } = renderPanel();
    const headings = within(dialog)
      .getAllByRole('button', { expanded: true })
      .map((b) => b.textContent);
    expect(headings).toEqual(['Цена, ₽', 'Производители', 'Категория', 'Акция']);
    expect(within(dialog).getByRole('button', { name: 'Механизм трансформации' })).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });

  it('выбор копится; «Показать N» применяет и закрывает', async () => {
    const { dialog, onQueryChange, onClose } = renderPanel('?sort=cheap');
    expect(within(dialog).getByRole('button', { name: 'Показать 226 товаров' })).toBeEnabled();

    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'АСМ' }));
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Диваны угловые' }));
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'По акции' }));
    expect(onQueryChange).not.toHaveBeenCalled();

    await act(() => vi.advanceTimersByTimeAsync(400));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Показать 42 товара' }));

    expect(onQueryChange).toHaveBeenCalledWith(
      parseCatalogQuery('?sort=cheap&manufacturers=asm&category=uglovye&on_sale=1'),
    );
    expect(onClose).toHaveBeenCalled();
  });

  it('атрибуты: значение без товаров выключено', () => {
    const { dialog } = renderPanel();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Механизм трансформации' }));
    expect(within(dialog).getByRole('checkbox', { name: 'Еврокнижка' })).toBeEnabled();
    expect(within(dialog).getByRole('checkbox', { name: 'Аккордеон' })).toBeDisabled();
  });

  it('«Сбросить все фильтры» очищает черновик, не применяет', () => {
    const { dialog, onQueryChange } = renderPanel('?manufacturers=rai&on_sale=1');
    expect(within(dialog).getByRole('checkbox', { name: 'Мебельный Рай' })).toBeChecked();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Сбросить все фильтры' }));

    expect(within(dialog).getByRole('checkbox', { name: 'Мебельный Рай' })).not.toBeChecked();
    expect(within(dialog).getByRole('checkbox', { name: 'По акции' })).not.toBeChecked();
    expect(onQueryChange).not.toHaveBeenCalled();
  });

  it('закрытие крестиком — без применения', () => {
    const { dialog, onQueryChange, onClose } = renderPanel();
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'АСМ' }));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Закрыть' }));
    expect(onClose).toHaveBeenCalled();
    expect(onQueryChange).not.toHaveBeenCalled();
  });

  it('ниже 980 — шторка «Фильтры» снизу, «Сбросить» в заголовке', () => {
    compact = true;
    const { dialog } = renderPanel('?on_sale=1');
    expect(within(dialog).getByRole('heading', { name: 'Фильтры' })).toBeInTheDocument();
    expect(screen.queryByTestId('sheet-grip')).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Закрыть' })).not.toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Сбросить' }));
    expect(within(dialog).getByRole('checkbox', { name: 'По акции' })).not.toBeChecked();
  });
});
