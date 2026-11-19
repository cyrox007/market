import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import CheckoutAddressModal from './CheckoutAddressModal';
import { api } from '../../../lib/api';

vi.mock('../../../lib/api', () => ({
  api: {
    addressDirectory: { options: vi.fn() },
    addresses: { create: vi.fn(), setDefault: vi.fn() },
  },
}));
beforeEach(() => {
  window.matchMedia = vi
    .fn()
    .mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
  vi.clearAllMocks();
  vi.mocked(api.addressDirectory.options).mockImplementation(async (level) => ({
    data:
      level === 'streets'
        ? [{ externalId: 'street-id', name: 'Советская', label: 'ул. Советская' }]
        : level === 'buildings'
          ? [{ externalId: 'house-id', name: '64', label: 'д. 64' }]
          : [],
  }));
  vi.mocked(api.addresses.create).mockResolvedValue({
    address: { id: 8, is_default: false, directory_external_id: 'house-id' },
  });
  vi.mocked(api.addresses.setDefault).mockResolvedValue({ message: 'OK' });
});
it('saves an authenticated address only from a directory building, then makes it default', async () => {
  const saved = vi.fn();
  render(
    <CheckoutAddressModal
      open
      authenticated
      onClose={vi.fn()}
      onSaved={saved}
      initialCity={{ externalId: 'city-id', name: 'Липецк', label: 'г. Липецк' }}
    />,
  );
  expect(screen.getByRole('button', { name: 'Сохранить адрес' })).toBeDisabled();
  fireEvent.focus(screen.getByRole('combobox', { name: 'Улица' }));
  fireEvent.click(await screen.findByRole('option', { name: 'ул. Советская' }));
  fireEvent.focus(screen.getByRole('combobox', { name: 'Дом / корпус / строение' }));
  fireEvent.click(await screen.findByRole('option', { name: 'д. 64' }));
  fireEvent.change(screen.getByLabelText('Квартира'), { target: { value: '17' } });
  fireEvent.click(screen.getByRole('checkbox', { name: 'Сделать основным адресом' }));
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить адрес' }));
  await waitFor(() =>
    expect(saved).toHaveBeenCalledWith(expect.objectContaining({ id: 8, is_default: true })),
  );
  expect(api.addresses.create).toHaveBeenCalledWith(
    expect.objectContaining({ directory_external_id: 'house-id', apartment: '17' }),
  );
  expect(api.addresses.create).not.toHaveBeenCalledWith(
    expect.objectContaining({ city: expect.anything() }),
  );
  expect(api.addresses.setDefault).toHaveBeenCalledWith(8);
});

it('keeps a guest address local, supports a building without a street and invalidates an edited house', async () => {
  const saved = vi.fn();
  const parentSubmit = vi.fn();
  render(
    <form onSubmit={parentSubmit}>
      <input required aria-label="Имя покупателя" />
      <CheckoutAddressModal
        open
        authenticated={false}
        onClose={vi.fn()}
        onSaved={saved}
        initialCity={{ externalId: 'city-id', name: 'Липецк', label: 'г. Липецк' }}
      />
    </form>,
  );
  expect(screen.queryByLabelText('Название адреса')).not.toBeInTheDocument();
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  const house = screen.getByRole('combobox', { name: 'Дом / корпус / строение' });
  fireEvent.focus(house);
  await screen.findByRole('option', { name: 'д. 64' });
  fireEvent.keyDown(house, { key: 'ArrowDown' });
  fireEvent.keyDown(house, { key: 'Enter' });
  expect(screen.getByRole('button', { name: 'Сохранить адрес' })).toBeEnabled();
  fireEvent.change(house, { target: { value: '65' } });
  expect(screen.getByRole('button', { name: 'Сохранить адрес' })).toBeDisabled();
  fireEvent.click(await screen.findByRole('option', { name: 'д. 64' }));
  fireEvent.change(screen.getByLabelText('Этаж'), { target: { value: '5' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить адрес' }));
  await waitFor(() =>
    expect(saved).toHaveBeenCalledWith(
      expect.objectContaining({
        id: -1,
        directory_external_id: 'house-id',
        full_address: 'г. Липецк, д. 64',
        floor: '5',
      }),
    ),
  );
  expect(api.addresses.create).not.toHaveBeenCalled();
  expect(api.addresses.setDefault).not.toHaveBeenCalled();
  expect(parentSubmit).not.toHaveBeenCalled();
});

it('retries setting the default after create succeeded without creating the address twice', async () => {
  const saved = vi.fn();
  vi.mocked(api.addresses.setDefault)
    .mockRejectedValueOnce(new Error('Попробуйте ещё раз'))
    .mockResolvedValueOnce({ message: 'OK' });
  render(
    <CheckoutAddressModal
      open
      authenticated
      onClose={vi.fn()}
      onSaved={saved}
      initialCity={{ externalId: 'city-id', name: 'Липецк', label: 'г. Липецк' }}
    />,
  );
  fireEvent.focus(screen.getByRole('combobox', { name: 'Дом / корпус / строение' }));
  fireEvent.click(await screen.findByRole('option', { name: 'д. 64' }));
  fireEvent.click(screen.getByRole('checkbox', { name: 'Сделать основным адресом' }));
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить адрес' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Адрес сохранён. Попробуйте ещё раз');
  expect(saved).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить адрес' }));
  await waitFor(() =>
    expect(saved).toHaveBeenCalledWith(expect.objectContaining({ id: 8, is_default: true })),
  );
  expect(api.addresses.create).toHaveBeenCalledTimes(1);
  expect(api.addresses.setDefault).toHaveBeenCalledTimes(2);
});
