import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { parseCatalogQuery } from '../../../../lib/catalog-query';
import EmptyResults from './EmptyResults';

const show = (search: string, sectionTotal: number | null = 226) => {
  const onReset = vi.fn();
  render(
    <EmptyResults
      query={parseCatalogQuery(search)}
      sectionTotal={sectionTotal}
      onReset={onReset}
    />,
  );
  return onReset;
};

describe('пустая выдача', () => {
  it('с диапазоном цены — подсказка про цену и число товаров в разделе, одним предложением (Figma 730:60501)', () => {
    show('price_min=900000&manufacturers=asm');
    expect(screen.getByText('По вашим фильтрам ничего не нашлось')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Попробуйте расширить диапазон цены или снять часть фильтров, всего в разделе 226 товаров.',
      ),
    ).toBeInTheDocument();
  });

  it('с габаритами и ценой — про оба диапазона', () => {
    show('price_max=100&width_min=5000');
    expect(screen.getByText(/расширить диапазон габаритов \/ цены/)).toBeInTheDocument();
  });

  it('без диапазонов — только снять фильтры; число раздела неизвестно — без него', () => {
    show('manufacturers=asm', null);
    expect(screen.getByText('Попробуйте снять часть фильтров.')).toBeInTheDocument();
  });

  it('«Сбросить фильтры» снимает фильтры', () => {
    const onReset = show('manufacturers=asm');
    fireEvent.click(screen.getByRole('button', { name: 'Сбросить фильтры' }));
    expect(onReset).toHaveBeenCalled();
  });

  it('без фильтров — в разделе пока нет товаров, сбрасывать нечего', () => {
    show('sort=cheap');
    expect(screen.getByText('В этом разделе пока нет товаров')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Сбросить фильтры' })).not.toBeInTheDocument();
  });
});
