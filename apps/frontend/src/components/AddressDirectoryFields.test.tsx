import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import CheckoutAddress from './CheckoutAddress';
import { api } from '../lib/api';
vi.mock('../lib/api', () => ({
  api: { localities: { search: vi.fn() }, addressDirectory: { options: vi.fn() } },
}));
beforeEach(() => vi.resetAllMocks());
afterEach(cleanup);
it('selects a real building and clears that selection as soon as the city is changed', async () => {
  const change = vi.fn();
  vi.mocked(api.localities.search).mockResolvedValue({
    data: [{ externalId: 'city-id', name: 'Липецк', label: 'г. Липецк' }],
  } as never);
  vi.mocked(api.addressDirectory.options).mockImplementation(async (level) => ({
    data:
      level === 'streets'
        ? [{ externalId: 'street-id', name: 'Советская', label: 'ул. Советская' }]
        : [{ externalId: 'house-id', name: '64', label: 'д. 64' }],
  }));
  render(<CheckoutAddress appearance="account" onChange={change} />);
  fireEvent.change(screen.getByRole('combobox', { name: 'Город' }), { target: { value: 'Лип' } });
  fireEvent.click(await screen.findByRole('option', { name: 'г. Липецк' }));
  fireEvent.change(screen.getByRole('combobox', { name: 'Улица и дом' }), {
    target: { value: 'Сов' },
  });
  fireEvent.click(await screen.findByRole('option', { name: 'ул. Советская' }));
  fireEvent.change(screen.getByRole('combobox', { name: 'Дом / корпус / строение' }), {
    target: { value: '64' },
  });
  fireEvent.click(await screen.findByRole('option', { name: 'д. 64' }));
  expect(change).toHaveBeenLastCalledWith('house-id');
  expect(api.addressDirectory.options).toHaveBeenLastCalledWith(
    'buildings',
    'street-id',
    '64',
    expect.any(AbortSignal),
  );
  fireEvent.click(screen.getByRole('button', { name: 'Изменить город' }));
  expect(change).toHaveBeenLastCalledWith(null);
  expect(screen.queryByText('Выбран дом: д. 64')).not.toBeInTheDocument();
});
it('keeps a saved address editable without directory requests until the destination is changed', () => {
  const change = vi.fn();
  render(
    <CheckoutAddress
      appearance="account"
      initialAddress={{
        id: 1,
        city: 'Липецк',
        street: 'Советская',
        house: '64',
        is_default: false,
      }}
      onChange={change}
    />,
  );
  expect(screen.getByLabelText('Улица и дом')).toHaveValue('Советская, 64');
  expect(api.localities.search).not.toHaveBeenCalled();
  expect(change).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('button', { name: 'Изменить город, улицу или дом' }));
  expect(change).toHaveBeenCalledWith(null);
  expect(screen.getByRole('combobox', { name: 'Город' })).toBeInTheDocument();
});
it('shows a directory error separately from empty results and retries the same query', async () => {
  vi.mocked(api.localities.search)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ data: [] });
  render(<CheckoutAddress appearance="account" onChange={vi.fn()} />);
  fireEvent.change(screen.getByRole('combobox', { name: 'Город' }), { target: { value: 'Лип' } });
  expect(await screen.findByRole('alert')).toHaveTextContent('Справочник временно недоступен');
  expect(screen.queryByText('Ничего не найдено. Уточните запрос.')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить поиск' }));
  expect(await screen.findByText('Ничего не найдено. Уточните запрос.')).toBeInTheDocument();
  await waitFor(() => expect(api.localities.search).toHaveBeenCalledTimes(2));
});
it('exposes the active option only while the popup is open and keeps options out of the Tab order', async () => {
  vi.mocked(api.localities.search).mockResolvedValue({
    data: [{ externalId: 'city', name: 'Липецк', label: 'г. Липецк' }],
  } as never);
  render(<CheckoutAddress appearance="account" onChange={vi.fn()} />);
  const input = screen.getByRole('combobox', { name: 'Город' });
  fireEvent.change(input, { target: { value: 'Лип' } });
  const option = await screen.findByRole('option', { name: 'г. Липецк' });
  expect(option).toHaveAttribute('tabindex', '-1');
  fireEvent.keyDown(input, { key: 'ArrowDown' });
  expect(input).toHaveAttribute('aria-activedescendant', option.id);
  fireEvent.keyDown(input, { key: 'Escape' });
  expect(input).not.toHaveAttribute('aria-activedescendant');
  expect(screen.queryByRole('option')).not.toBeInTheDocument();
});
