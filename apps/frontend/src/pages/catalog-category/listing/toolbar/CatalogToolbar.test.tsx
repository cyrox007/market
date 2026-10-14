import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FiltersMeta } from '../../../../lib/api';
import { parseCatalogQuery, type CatalogQuery } from '../../../../lib/catalog-query';
import CatalogToolbar from './CatalogToolbar';

let compact = false;

beforeAll(() => {
  // jsdom без matchMedia; compact — экран уже 980
  window.matchMedia = ((query: string) => ({
    matches: compact && query.includes('979.98'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

beforeEach(() => {
  compact = false;
});

const meta: FiltersMeta = {
  price: { min: 890, max: 180350 },
  colors: [],
  sizes: [],
  attributes: [],
};

function renderToolbar(search = '', options: { meta?: FiltersMeta; count?: number } = {}) {
  const onQueryChange = vi.fn<(next: CatalogQuery) => void>();
  const onOpenAllFilters = vi.fn();
  const countProducts = vi.fn(() => Promise.resolve(options.count ?? 42));
  render(
    <CatalogToolbar
      compact={compact}
      query={parseCatalogQuery(search)}
      onQueryChange={onQueryChange}
      meta={options.meta ?? meta}
      total={226}
      countProducts={countProducts}
      onOpenAllFilters={onOpenAllFilters}
    />,
  );
  return { onQueryChange, onOpenAllFilters, countProducts };
}

describe('строка фильтров', () => {
  it('сортировка применяется сразу и возвращает на первую страницу', async () => {
    const { onQueryChange } = renderToolbar('?page=3');
    fireEvent.click(screen.getByRole('button', { name: 'По популярности' }));
    fireEvent.click(screen.getByRole('radio', { name: 'Сначала дешевле' }));

    expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?sort=cheap'));
    await waitFor(() => expect(screen.queryByRole('radio')).not.toBeInTheDocument());
  });

  describe('окно «Цена»', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('выбор копится, число обновляется, «Показать N» применяет', async () => {
      const { onQueryChange, countProducts } = renderToolbar('?sort=cheap&page=2');
      fireEvent.click(screen.getByRole('button', { name: 'Цена' }));
      const dialog = screen.getByRole('dialog', { name: 'Цена' });
      expect(within(dialog).getByRole('button', { name: 'Показать 226 товаров' })).toBeEnabled();

      const from = within(dialog).getByRole('textbox', { name: 'Цена от' });
      fireEvent.change(from, { target: { value: '10000' } });
      fireEvent.blur(from);
      expect(onQueryChange).not.toHaveBeenCalled();

      await act(() => vi.advanceTimersByTimeAsync(400));
      expect(countProducts).toHaveBeenCalledTimes(1);
      fireEvent.click(within(dialog).getByRole('button', { name: 'Показать 42 товара' }));

      expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?sort=cheap&price_min=10000'));
      await act(() => vi.advanceTimersByTimeAsync(200));
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('Esc закрывает без применения, при новом открытии — применённые значения', () => {
      const { onQueryChange } = renderToolbar('?price_max=50000');
      fireEvent.click(screen.getByRole('button', { name: 'Цена' }));
      const from = screen.getByRole('textbox', { name: 'Цена от' });
      fireEvent.change(from, { target: { value: '10000' } });
      fireEvent.blur(from);
      fireEvent.keyDown(document, { key: 'Escape' });

      expect(onQueryChange).not.toHaveBeenCalled();
      fireEvent.click(screen.getByRole('button', { name: 'Цена' }));
      expect(screen.getByRole('textbox', { name: 'Цена от' })).toHaveValue('');
      expect(screen.getByRole('textbox', { name: 'Цена до' })).toHaveValue('50 000');
    });

    it('пока число считается — «Показать товары», применить можно', () => {
      const { onQueryChange } = renderToolbar();
      fireEvent.click(screen.getByRole('button', { name: 'Цена' }));
      const from = screen.getByRole('textbox', { name: 'Цена от' });
      fireEvent.change(from, { target: { value: '10000' } });
      fireEvent.blur(from);

      const show = screen.getByRole('button', { name: 'Показать товары' });
      expect(show).toBeEnabled();
      fireEvent.click(show);
      expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?price_min=10000'));
    });

    it('ничего не найдено — кнопка выключена', async () => {
      renderToolbar('', { count: 0 });
      fireEvent.click(screen.getByRole('button', { name: 'Цена' }));
      const from = screen.getByRole('textbox', { name: 'Цена от' });
      fireEvent.change(from, { target: { value: '170000' } });
      fireEvent.blur(from);
      await act(() => vi.advanceTimersByTimeAsync(400));
      expect(screen.getByRole('button', { name: 'Ничего не найдено' })).toBeDisabled();
    });
  });

  it('выбранная цена — кнопка «Цена» видна, даже если диапазон в meta схлопнулся', () => {
    renderToolbar('?price_min=30000', { meta: { ...meta, price: { min: 34990, max: 34990 } } });
    expect(screen.getByRole('button', { name: 'Цена' })).toBeInTheDocument();
  });

  it('без выбранной цены и с одной ценой в категории — «Цена» не нужна', () => {
    renderToolbar('', { meta: { ...meta, price: { min: 34990, max: 34990 } } });
    expect(screen.queryByRole('button', { name: 'Цена' })).not.toBeInTheDocument();
  });

  describe('фильтры, зависящие от бэкенда', () => {
    const full: FiltersMeta = {
      ...meta,
      dimensions: {
        width: { min: 224, max: 23500 },
        depth: { min: 450, max: 1070 },
        height: { min: 107, max: 1680 },
      },
      // Производитель в API — характеристика (market-docs/44, ответ 3)
      attributes: [
        {
          id: 9,
          name: 'Производитель',
          slug: 'proizvoditel',
          values: [
            { id: 1, name: 'Мебельный Рай', slug: 'rai' },
            { id: 2, name: 'АСМ', slug: 'asm' },
          ],
        },
      ],
      has_sale: true,
    };

    it('без данных в meta — не показываются', () => {
      renderToolbar();
      expect(screen.getByRole('button', { name: 'Цена' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Габариты' })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Производители' })).not.toBeInTheDocument();
      expect(screen.queryByRole('switch', { name: 'По акции' })).not.toBeInTheDocument();
    });

    it('габариты: три пары полей, применяются кнопкой', () => {
      const { onQueryChange } = renderToolbar('', { meta: full });
      fireEvent.click(screen.getByRole('button', { name: 'Габариты' }));
      const width = screen.getByRole('textbox', { name: 'Ширина от' });
      expect(screen.getByRole('textbox', { name: 'Высота до' })).toHaveAttribute(
        'placeholder',
        'до 1 680',
      );
      fireEvent.change(width, { target: { value: '2400' } });
      fireEvent.blur(width);
      fireEvent.click(screen.getByRole('button', { name: /^Показать/ }));
      expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?width_min=2400'));
    });

    it('производители: можно выбрать несколько (market-docs/44, ответ 3)', () => {
      const { onQueryChange } = renderToolbar('?manufacturers=rai', { meta: full });
      fireEvent.click(screen.getByRole('button', { name: 'Производители' }));
      expect(screen.getByRole('checkbox', { name: 'Мебельный Рай' })).toBeChecked();
      fireEvent.click(screen.getByRole('checkbox', { name: 'АСМ' }));
      expect(screen.getByRole('checkbox', { name: 'Мебельный Рай' })).toBeChecked();
      fireEvent.click(screen.getByRole('button', { name: /^Показать/ }));
      expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?manufacturers=rai,asm'));
    });

    it('«По акции» применяется сразу', () => {
      const { onQueryChange } = renderToolbar('?page=2', { meta: full });
      fireEvent.click(screen.getByRole('switch', { name: 'По акции' }));
      expect(onQueryChange).toHaveBeenCalledWith(parseCatalogQuery('?on_sale=1'));
    });
  });

  it('«Фильтры» показывает число выбранных и открывает все фильтры', () => {
    const { onOpenAllFilters } = renderToolbar('?price_min=1000&colors=a,b&sort=cheap');
    const button = screen.getByRole('button', { name: 'Фильтры, выбрано 3' });
    fireEvent.click(button);
    expect(onOpenAllFilters).toHaveBeenCalled();
  });

  it('ниже 980 — только сортировка и «Фильтры»; сортировка — шторкой с крестиком', async () => {
    compact = true;
    const { onQueryChange } = renderToolbar();
    expect(screen.getByRole('button', { name: 'По популярности' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Фильтры' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Цена' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'По популярности' }));
    expect(screen.getByTestId('sheet-grip')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Закрыть' }));
    await waitFor(() => expect(screen.queryByRole('radio')).not.toBeInTheDocument());
    expect(onQueryChange).not.toHaveBeenCalled();
  });
});
