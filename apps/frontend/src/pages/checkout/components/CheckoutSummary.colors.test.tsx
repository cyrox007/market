import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import CheckoutSummary, { type CheckoutSummaryState } from './CheckoutSummary';
import type { ProductColorRow } from '../../../lib/api';

const colors = (name: string): ProductColorRow[] => [
  { element: null, solution: { id: 1, name, slug: name, icon_url: null, base_colors: [] } },
];

it('uses the physical component colors for direct bundles instead of repeating the root color', () => {
  const checkout: CheckoutSummaryState = {
    direct: true,
    cart: undefined,
    directProduct: null,
    items: [
      { product_id: 11, quantity: 1 },
      { product_id: 12, quantity: 1 },
    ],
    prepared: {
      items: [
        { product_id: 11, quantity: 1 },
        { product_id: 12, quantity: 1 },
      ],
      subtotal: 300,
      colors: colors('Цвет корня'),
      priced_components: [
        {
          product_id: 11,
          name: 'Каркас',
          sku: 'frame',
          quantity: 1,
          unit_price: 100,
          total: 100,
          colors: colors('Дуб каркаса'),
        },
        {
          product_id: 12,
          name: 'Фасад',
          sku: 'facade',
          quantity: 1,
          unit_price: 200,
          total: 200,
          colors: colors('Белый фасад'),
        },
      ],
    },
    subtotal: 300,
    total: 300,
    fulfillment: null,
    mode: 'pickup',
    calculating: false,
    selected: null,
    hasUnavailable: false,
    busy: false,
    canSubmit: false,
  };
  render(<CheckoutSummary checkout={checkout} />);
  expect(screen.getByText('Дуб каркаса')).toBeInTheDocument();
  expect(screen.getByText('Белый фасад')).toBeInTheDocument();
  expect(screen.queryByText('Цвет корня')).not.toBeInTheDocument();
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('300 ₽');
});
