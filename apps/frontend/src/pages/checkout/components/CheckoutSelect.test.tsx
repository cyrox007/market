import { fireEvent, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import CheckoutSelect from './CheckoutSelect';

it('selects with the keyboard, skips disabled options and closes without submitting the form', () => {
  const change = vi.fn();
  const submit = vi.fn();
  render(
    <form onSubmit={submit}>
      <CheckoutSelect
        label="Способ оплаты"
        value="cash"
        onChange={change}
        options={[
          { value: 'cash', label: 'Наличные' },
          { value: 'soon', label: 'Скоро', disabled: true },
          { value: 'card', label: 'Карта' },
        ]}
      />
    </form>,
  );
  const field = screen.getByRole('combobox', { name: 'Способ оплаты' });
  fireEvent.click(field);
  expect(screen.getByRole('option', { name: 'Скоро' })).toHaveAttribute('aria-disabled', 'true');
  fireEvent.keyDown(field, { key: 'ArrowDown' });
  fireEvent.keyDown(field, { key: 'Enter' });
  expect(change).toHaveBeenCalledWith('card');
  expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  expect(submit).not.toHaveBeenCalled();
  fireEvent.click(field);
  fireEvent.keyDown(field, { key: 'Escape' });
  expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  fireEvent.click(field);
  fireEvent.pointerDown(document.body);
  expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
});
