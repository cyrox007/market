import { fireEvent, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import CheckoutAddress from './CheckoutAddress';

it('uses a restored header city without replacing a city the user has started typing', () => {
  const onChange = vi.fn();
  const { rerender } = render(<CheckoutAddress onChange={onChange} initialCity={null} />);
  rerender(
    <CheckoutAddress
      onChange={onChange}
      initialCity={{ externalId: 'city', name: 'Липецк', label: 'Липецк' }}
    />,
  );
  expect(screen.getByRole('combobox', { name: 'Город' })).toHaveValue('Липецк');
  fireEvent.change(screen.getByRole('combobox', { name: 'Город' }), {
    target: { value: 'Москва' },
  });
  rerender(
    <CheckoutAddress
      onChange={onChange}
      initialCity={{ externalId: 'other', name: 'Елец', label: 'Елец' }}
    />,
  );
  expect(screen.getByRole('combobox', { name: 'Город' })).toHaveValue('Москва');
});
