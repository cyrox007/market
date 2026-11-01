import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import Checkout from './warehouse-page';
import { api } from '../../lib/api';
import { deferred } from '../../test/deferred';

const { navigate, reload, cart, auth } = vi.hoisted(() => ({
  navigate: vi.fn(),
  reload: vi.fn(),
  auth: { user: null as { id: number; name: string; email: string; phone: string } | null },
  cart: {
    subtotal: 1000,
    items: [
      { id: 1, product_id: 17, name: 'Стол', price: 1000, total: 1000, stock: 3, quantity: 1 },
    ],
  },
}));
vi.mock('react-router-dom', async () => ({
  ...(await vi.importActual('react-router-dom')),
  useNavigate: () => navigate,
}));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => auth }));
vi.mock('../../hooks/useCart', () => ({ useCart: () => ({ cart, reloadCart: reload }) }));
vi.mock('../../components/CheckoutAddress', () => ({
  default: ({ onChange }: { onChange: (id: string) => void }) => (
    <button type="button" onClick={() => onChange('house-guid')}>
      Выбрать дом
    </button>
  ),
}));
vi.mock('../../lib/api', () => ({
  api: {
    addresses: { list: vi.fn() },
    paymentMethods: { list: vi.fn() },
    orders: { create: vi.fn(), prepare: vi.fn() },
    shipping: { quote: vi.fn(), pickupOptions: vi.fn() },
  },
}));

beforeEach(() => {
  vi.clearAllMocks();
  window.matchMedia = vi
    .fn()
    .mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
  auth.user = null;
  vi.mocked(api.addresses.list).mockResolvedValue({ data: [] });
  vi.mocked(api.paymentMethods.list).mockResolvedValue({
    data: [{ id: 1, code: 'cash', name: 'Наличные', is_active: true, sort_order: 0 }],
  });
  vi.mocked(api.shipping.pickupOptions).mockResolvedValue({
    pickup: [{ warehouse_id: 4, store_id: 2, name: 'Липецк', address: 'Адрес магазина' }],
  });
  vi.mocked(api.shipping.quote).mockResolvedValue({
    available: true,
    subtotal: 1000,
    options: [],
    selected: {
      warehouse_id: 4,
      warehouse_name: 'Центральный',
      delivery_cost: 500,
      assembly_cost: 0,
      total_fulfillment_cost: 500,
      service_options: {
        assembly: { available: false, required: false, price: null },
        handling: [],
        additional: [],
      },
    },
  });
  vi.mocked(api.orders.create).mockResolvedValue({ order: { id: 99, can_pay: false } as never });
});
it('selects the default saved address and sends address_id without legacy location fields', async () => {
  auth.user = { id: 1, name: 'Иванов Иван', phone: '+79001234567', email: 'buyer@example.test' };
  vi.mocked(api.addresses.list).mockResolvedValue({
    data: [
      {
        id: 7,
        title: 'Дача',
        full_address: 'Другой адрес',
        is_default: false,
        directory_external_id: 'other-house',
      },
      {
        id: 8,
        title: 'Дом',
        full_address: 'Липецк, Советская, 64',
        is_default: true,
        directory_external_id: 'house-guid',
      },
    ],
  });
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await waitFor(() =>
    expect(api.shipping.quote).toHaveBeenCalledWith(expect.objectContaining({ address_id: 8 })),
  );
  fireEvent.click(screen.getByRole('checkbox'));
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() =>
    expect(api.orders.create).toHaveBeenCalledWith(
      expect.objectContaining({ address_id: 8, contact_name: 'Иванов Иван' }),
    ),
  );
  expect(vi.mocked(api.orders.create).mock.calls[0][0]).not.toHaveProperty('directory_external_id');
});
afterEach(cleanup);

it('shows digital ruble as coming soon and chooses an available payment method', async () => {
  vi.mocked(api.paymentMethods.list).mockResolvedValue({
    data: [
      {
        id: 2,
        code: 'digital_ruble',
        name: 'Цифровой рубль',
        is_active: false,
        is_coming_soon: true,
        selectable: false,
        sort_order: 0,
      },
      {
        id: 1,
        code: 'card',
        name: 'Банковская карта',
        is_active: true,
        selectable: true,
        sort_order: 1,
      },
    ],
  });
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await waitFor(() =>
    expect(screen.getByRole('combobox', { name: 'Способ оплаты' })).toHaveTextContent(
      'Банковская карта',
    ),
  );
  fireEvent.click(screen.getByRole('combobox', { name: 'Способ оплаты' }));
  const option = await screen.findByRole('option', { name: 'Цифровой рубль — Скоро' });
  expect(option).toHaveAttribute('aria-disabled', 'true');
  expect(screen.getByRole('combobox', { name: 'Способ оплаты' })).toHaveTextContent(
    'Банковская карта',
  );
});

it('cannot submit when only coming soon methods exist, including a forged selection', async () => {
  vi.mocked(api.paymentMethods.list).mockResolvedValue({
    data: [
      {
        id: 2,
        code: 'digital_ruble',
        name: 'Цифровой рубль',
        is_active: false,
        is_coming_soon: true,
        selectable: false,
        sort_order: 0,
      },
    ],
  });
  const { container } = render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await screen.findByText('Другие способы');
  fireEvent.click(screen.getByRole('combobox', { name: 'Способ оплаты' }));
  await screen.findByRole('option', { name: 'Цифровой рубль — Скоро' });
  fireEvent.click(screen.getByRole('checkbox'));
  fireEvent.click(screen.getByRole('option', { name: 'Цифровой рубль — Скоро' }));
  expect(
    (screen.getByRole('button', { name: 'Оформить заказ' }) as HTMLButtonElement).disabled,
  ).toBe(true);
  fireEvent.submit(container.querySelector('form')!);
  expect(api.orders.create).not.toHaveBeenCalled();
});

async function contacts() {
  fireEvent.change(screen.getByLabelText('Фамилия'), { target: { value: 'Иванов' } });
  fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Покупатель' } });
  fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+79001234567' } });
  fireEvent.change(screen.getByLabelText('Почта'), { target: { value: 'buyer@example.test' } });
  fireEvent.click(screen.getByRole('checkbox'));
  await waitFor(() =>
    expect(screen.getByRole('combobox', { name: 'Способ оплаты' })).toHaveTextContent('Наличные'),
  );
}
it('joins the three contact fields into the server contact_name without extra spaces', async () => {
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await contacts();
  fireEvent.change(screen.getByLabelText('Отчество'), { target: { value: ' Петрович ' } });
  fireEvent.click(screen.getByText('Выбрать дом'));
  await waitFor(() =>
    expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/),
  );
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() =>
    expect(api.orders.create).toHaveBeenCalledWith(
      expect.objectContaining({ contact_name: 'Иванов Покупатель Петрович' }),
    ),
  );
});
it('allows pickup after switching away from an unfinished delivery calculation', async () => {
  const pending = deferred<Awaited<ReturnType<typeof api.shipping.quote>>>();
  vi.mocked(api.shipping.quote).mockReturnValueOnce(pending.promise);
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await contacts();
  fireEvent.click(screen.getByText('Выбрать дом'));
  await screen.findByText('Рассчитываем доставку…');
  fireEvent.click(screen.getByRole('radio', { name: /Самовывоз/ }));
  fireEvent.click(screen.getByRole('combobox', { name: 'Точка самовывоза' }));
  fireEvent.click(await screen.findByRole('option', { name: 'Липецк — Адрес магазина' }));
  expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled();
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await act(async () => {
    pending.resolve({ available: false, subtotal: 1, options: [], selected: null });
  });
  await waitFor(() => expect(api.orders.create).toHaveBeenCalled());
  expect(
    screen.queryByText('Доставка по выбранному адресу недоступна для всего заказа.'),
  ).not.toBeInTheDocument();
});
it('submits the building and selected warehouse, without legacy shipping fields or client costs', async () => {
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await contacts();
  fireEvent.click(screen.getByText('Выбрать дом'));
  await waitFor(() =>
    expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/),
  );
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(body).toMatchObject({
    directory_external_id: 'house-guid',
    warehouse_id: 4,
    expected_subtotal: 1000,
  });
  expect(body).not.toHaveProperty('shipping_location_id');
  expect(body).not.toHaveProperty('delivery_cost');
  expect(body).not.toHaveProperty('items');
  await waitFor(() => expect(navigate).toHaveBeenCalledWith('/orders/99'));
});
it('submits pickup store and warehouse without a customer delivery address', async () => {
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await contacts();
  fireEvent.click(screen.getByRole('radio', { name: /Самовывоз/ }));
  fireEvent.click(screen.getByRole('combobox', { name: 'Точка самовывоза' }));
  fireEvent.click(await screen.findByRole('option', { name: 'Липецк — Адрес магазина' }));
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(body).toMatchObject({ delivery_type: 'pickup', warehouse_id: 4, store_id: 2 });
  expect(body).not.toHaveProperty('directory_external_id');
});
it('does not silently resubmit after a changed price', async () => {
  vi.mocked(api.orders.create).mockRejectedValueOnce({ status: 409 });
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  await contacts();
  fireEvent.click(screen.getByText('Выбрать дом'));
  await waitFor(() =>
    expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/),
  );
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() => expect(reload).toHaveBeenCalled());
  expect(api.orders.create).toHaveBeenCalledTimes(1);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled());
  expect(screen.getByRole('alert')).toHaveTextContent('Цена изменилась');
});
it('prefills the one-click form and sends its comment with the direct item', async () => {
  vi.mocked(api.orders.prepare).mockResolvedValue({
    items: [{ product_id: 42, quantity: 2 }],
    subtotal: 2000,
  });
  render(
    <MemoryRouter
      initialEntries={[
        {
          pathname: '/checkout',
          search: '?product_id=42&quantity=2',
          state: {
            oneClick: {
              name: 'Иванов Иван',
              phone: '+7 (912) 345-67-89',
              comment: 'Позвоните после 18',
            },
          },
        },
      ]}
    >
      <Checkout />
    </MemoryRouter>,
  );
  expect(screen.getByLabelText('Фамилия')).toHaveValue('Иванов');
  expect(screen.getByLabelText('Имя')).toHaveValue('Иван');
  expect(screen.getByLabelText('Телефон')).toHaveValue('+7 (912) 345-67-89');
  expect(screen.getByLabelText('Комментарий к заказу')).toHaveValue('Позвоните после 18');
  fireEvent.change(screen.getByLabelText('Почта'), { target: { value: 'buyer@example.test' } });
  fireEvent.click(screen.getByRole('checkbox'));
  await waitFor(() =>
    expect(screen.getByRole('combobox', { name: 'Способ оплаты' })).toHaveTextContent('Наличные'),
  );
  fireEvent.click(screen.getByText('Выбрать дом'));
  await waitFor(() =>
    expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/),
  );
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  expect(vi.mocked(api.orders.create).mock.calls[0][0]).toMatchObject({
    items: [{ product_id: 42, quantity: 2 }],
    comment: 'Позвоните после 18',
    contact_name: 'Иванов Иван',
    accept_offer: true,
    accept_personal_data: true,
  });
});

it('preserves a saved address without a directory GUID when switching delivery modes', async () => {
  auth.user = { id: 1, name: 'Иванов Иван', phone: '+79001234567', email: 'buyer@example.test' };
  vi.mocked(api.addresses.list).mockResolvedValue({
    data: [
      {
        id: 7,
        title: 'Основной',
        full_address: 'Первый адрес',
        is_default: true,
        directory_external_id: 'first',
      },
      { id: 8, title: 'Дача', full_address: 'Второй адрес', is_default: false },
    ],
  });
  render(
    <MemoryRouter>
      <Checkout />
    </MemoryRouter>,
  );
  fireEvent.click(await screen.findByRole('radio', { name: /Дача/ }));
  await waitFor(() =>
    expect(api.shipping.quote).toHaveBeenLastCalledWith(expect.objectContaining({ address_id: 8 })),
  );
  fireEvent.click(screen.getByRole('radio', { name: 'Самовывоз' }));
  fireEvent.click(screen.getByRole('radio', { name: 'Доставка' }));
  await waitFor(() => expect(screen.getByRole('radio', { name: /Дача/ })).toBeChecked());
  await waitFor(() =>
    expect(api.shipping.quote).toHaveBeenLastCalledWith(expect.objectContaining({ address_id: 8 })),
  );
});
