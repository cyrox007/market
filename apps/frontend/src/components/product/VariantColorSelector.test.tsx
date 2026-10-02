import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import VariantColorSelector from './VariantColorSelector';

const variant = (id: number, colors: Array<{ value: string; color_code: string | null }> = []) => ({
  id,
  price: 1000,
  in_stock: true,
  colors: colors.map((c, i) => ({ id: i + 1, slug: `c-${i}`, ...c })),
});

describe('VariantColorSelector', () => {
  it('не рисует блок «Цвет:», если у вариантов нет цветов (объединённые товары)', () => {
    const { container } = render(
      <VariantColorSelector
        variants={[variant(1), variant(2)]}
        selectedVariantId={1}
        onSelect={vi.fn()}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it('рисует палитру, если у вариантов есть цвета', () => {
    render(
      <VariantColorSelector
        variants={[
          variant(1, [{ value: 'Серый', color_code: '#808080' }]),
          variant(2, [{ value: 'Бежевый', color_code: null }]),
        ]}
        selectedVariantId={1}
        onSelect={vi.fn()}
      />,
    );

    expect(screen.getByText('Цвет:')).toBeInTheDocument();
    expect(screen.getByText('Серый')).toBeInTheDocument();
    expect(screen.getByText('Бежевый')).toBeInTheDocument();
  });
});
