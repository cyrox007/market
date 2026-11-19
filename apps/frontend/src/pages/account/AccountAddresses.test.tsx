import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { api, type Address } from '../../lib/api';
import AccountAddresses from './AccountAddresses';
vi.mock('../../lib/api', () => ({
  api: { addresses: { setDefault: vi.fn(), delete: vi.fn(), create: vi.fn(), update: vi.fn() } },
}));
vi.mock('../../components/CheckoutAddress', () => ({
  default: ({ onChange }: { onChange: (id: string | null) => void }) => (
    <button type="button" onClick={() => onChange('new-building')}>
      Выбрать здание
    </button>
  ),
}));
const addresses: Address[] = [
  {
    id: 1,
    title: 'Дом',
    city: 'Липецк',
    street: 'Советская',
    house: '64',
    full_address: 'г. Липецк, ул. Советская, д. 64',
    floor: '5',
    entrance: '2',
    is_default: true,
    directory_external_id: 'building-1',
  },
  { id: 2, title: 'Работа', full_address: 'г. Липецк, ул. Ленина, д. 1', is_default: false },
];
beforeEach(() => vi.resetAllMocks());
afterEach(cleanup);
vi.mock('../../hooks/useMediaQuery', () => ({ useMediaMatch: () => false }));
it('shows the real addresses and makes a non-default address primary through the existing API', async () => {
  const reload = vi.fn().mockResolvedValue(undefined);
  vi.mocked(api.addresses.setDefault).mockResolvedValue({ message: 'OK' });
  render(<AccountAddresses addresses={addresses} isLoading={false} onReload={reload} />);
  expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Адреса доставки');
  expect(screen.getByText('2 адреса')).toBeInTheDocument();
  expect(screen.getByText('Подъезд 2, этаж 5')).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Сделать основным' }));
  await waitFor(() => expect(api.addresses.setDefault).toHaveBeenCalledWith(2));
  await waitFor(() => expect(reload).toHaveBeenCalledTimes(1));
});
it('keeps a saved ID when making the new address primary fails, so retry never creates a duplicate', async () => {
  const reload = vi.fn().mockResolvedValue(undefined);
  vi.mocked(api.addresses.create).mockResolvedValue({ address: { id: 9 } } as never);
  vi.mocked(api.addresses.setDefault)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ message: 'OK' });
  render(<AccountAddresses addresses={[]} isLoading={false} onReload={reload} />);
  fireEvent.click(screen.getByRole('button', { name: 'Добавить новый адрес' }));
  const dialog = await screen.findByRole('dialog', { name: 'Новый адрес' });
  fireEvent.click(within(dialog).getByText('Выбрать здание'));
  fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Сделать основным адресом' }));
  fireEvent.click(within(dialog).getByRole('button', { name: 'Добавить адрес' }));
  expect(await within(dialog).findByRole('alert')).toHaveTextContent('Адрес сохранён');
  fireEvent.click(within(dialog).getByRole('button', { name: 'Повторить' }));
  await waitFor(() => expect(api.addresses.setDefault).toHaveBeenCalledTimes(2));
  expect(api.addresses.create).toHaveBeenCalledTimes(1);
  expect(api.addresses.setDefault).toHaveBeenLastCalledWith(9);
});
it('requires confirmation, keeps the dialog on deletion failure and retries without hiding the address', async () => {
  const reload = vi.fn().mockResolvedValue(undefined);
  vi.mocked(api.addresses.delete)
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce({ message: 'OK' });
  render(<AccountAddresses addresses={addresses} isLoading={false} onReload={reload} />);
  fireEvent.click(screen.getByRole('button', { name: 'Удалить адрес Дом' }));
  const dialog = await screen.findByRole('dialog', { name: 'Удалить адрес?' });
  expect(api.addresses.delete).not.toHaveBeenCalled();
  fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }));
  expect(await within(dialog).findByRole('alert')).toHaveTextContent('Не удалось удалить адрес');
  expect(screen.getByRole('article', { name: 'Дом' })).toBeInTheDocument();
  fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }));
  await waitFor(() => expect(reload).toHaveBeenCalledTimes(1));
  expect(api.addresses.delete).toHaveBeenCalledTimes(2);
});
it('edits extra fields of an existing address without sending legacy text or requiring the directory', async () => {
  vi.mocked(api.addresses.update).mockResolvedValue({ address: addresses[0] });
  render(
    <AccountAddresses
      addresses={addresses}
      isLoading={false}
      onReload={vi.fn().mockResolvedValue(undefined)}
    />,
  );
  fireEvent.click(screen.getByRole('button', { name: 'Редактировать адрес Дом' }));
  const dialog = await screen.findByRole('dialog', { name: 'Изменить адрес' });
  fireEvent.change(within(dialog).getByLabelText('Этаж'), { target: { value: '8' } });
  fireEvent.click(within(dialog).getByRole('button', { name: 'Сохранить адрес' }));
  await waitFor(() =>
    expect(api.addresses.update).toHaveBeenCalledWith(1, expect.objectContaining({ floor: '8' })),
  );
  const body = vi.mocked(api.addresses.update).mock.calls[0][1];
  expect(body).not.toHaveProperty('directory_external_id');
  expect(body).not.toHaveProperty('city');
  expect(body).not.toHaveProperty('room');
  expect(api.addresses.setDefault).not.toHaveBeenCalled();
});
it('retries only list refresh when deletion succeeded but reading the new list failed', async () => {
  const reload = vi
    .fn()
    .mockRejectedValueOnce(new Error('offline'))
    .mockResolvedValueOnce(undefined);
  vi.mocked(api.addresses.delete).mockResolvedValue({ message: 'OK' });
  render(<AccountAddresses addresses={addresses} isLoading={false} onReload={reload} />);
  fireEvent.click(screen.getByRole('button', { name: 'Удалить адрес Дом' }));
  const dialog = await screen.findByRole('dialog', { name: 'Удалить адрес?' });
  fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }));
  expect(await within(dialog).findByRole('alert')).toHaveTextContent('Адрес удалён');
  fireEvent.click(within(dialog).getByRole('button', { name: 'Обновить список' }));
  await waitFor(() => expect(reload).toHaveBeenCalledTimes(2));
  expect(api.addresses.delete).toHaveBeenCalledTimes(1);
});
it('does not repeat the entrance already present in the server full address', () => {
  render(
    <AccountAddresses
      addresses={[
        {
          ...addresses[0],
          address_snapshot: { label: 'г. Липецк, ул. Советская, д. 64' },
          apartment: '17',
          full_address: 'г. Липецк, ул. Советская, д. 64, кв. 17, подъезд 2',
        },
      ]}
      isLoading={false}
      onReload={vi.fn()}
    />,
  );
  expect(screen.getByText('г. Липецк, ул. Советская, д. 64, кв. 17')).toBeInTheDocument();
  expect(screen.getByText('Подъезд 2, этаж 5')).toBeInTheDocument();
  expect(screen.queryByText(/кв. 17, подъезд 2/)).not.toBeInTheDocument();
});
