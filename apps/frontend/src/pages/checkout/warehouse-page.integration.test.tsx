import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Checkout from './warehouse-page';
import { api } from '../../lib/api';
import type { Cart, WarehouseQuote } from '../../lib/api';
import { AuthProvider } from '../../contexts/AuthContext';
import { RegionProvider } from '../../contexts/RegionContext';
import { CountersProvider } from '../../contexts/CountersContext';
import { CartPanelProvider } from '../../contexts/CartPanelContext';
import { CartToastProvider } from '../../contexts/CartToastContext';
import { resetSessionQueue } from '../../lib/session-queue';
import { deferred } from '../../test/deferred';

vi.mock('../../lib/api', () => ({
  api: {
    cart: { get: vi.fn(), count: vi.fn() },
    addresses: { list: vi.fn(), create: vi.fn(), setDefault: vi.fn() },
    addressDirectory: { options: vi.fn() },
    paymentMethods: { list: vi.fn() },
    shipping: { quote: vi.fn(), pickupOptions: vi.fn() },
    orders: { create: vi.fn() },
  },
}));

const cart: Cart = {
  items: [
    {
      id: 1,
      product_id: 17,
      name: 'Стол',
      slug: 'stol',
      image: null,
      sku: null,
      price: 1000,
      total: 1000,
      quantity: 1,
      stock: 3,
      in_stock: true,
      stock_known: true,
    },
  ],
  subtotal: 1000,
  item_count: 1,
  is_empty: false,
};
const quote: WarehouseQuote = {
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
};

beforeEach(() => {
  vi.resetAllMocks();
  resetSessionQueue();
  localStorage.clear();
  sessionStorage.clear();
  window.matchMedia = vi.fn().mockImplementation((media: string) => ({
    media,
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  }));
  vi.mocked(api.cart.get).mockResolvedValue(cart);
  vi.mocked(api.paymentMethods.list).mockResolvedValue({
    data: [{ id: 1, code: 'cash', name: 'Наличные', is_active: true, sort_order: 0 }],
  });
  vi.mocked(api.shipping.pickupOptions).mockResolvedValue({
    pickup: [{ warehouse_id: 4, store_id: 2, name: 'Липецк', address: 'Адрес магазина' }],
  });
  vi.mocked(api.shipping.quote).mockResolvedValue(quote);
  vi.mocked(api.addressDirectory.options).mockImplementation(async (level) => ({
    data:
      level === 'localities'
        ? [{ externalId: 'city-guid', name: 'Липецк', label: 'г. Липецк' }]
        : level === 'streets'
          ? [{ externalId: 'street-guid', name: 'Советская', label: 'ул. Советская' }]
          : [{ externalId: 'house-guid', name: '64', label: 'д. 64' }],
  }));
});
afterEach(cleanup);

function renderCheckout() {
  return render(
    <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
      <MemoryRouter initialEntries={['/checkout']}>
        <CartPanelProvider>
          <CartToastProvider>
            <CountersProvider initialCounters={{ cartCount: 1, wishlistCount: 0, compareCount: 0 }}>
              <RegionProvider>
                <AuthProvider authChecked>
                  <Routes>
                    <Route path="/checkout" element={<Checkout />} />
                    <Route path="/orders/:id" element={<p>Заказ принят</p>} />
                  </Routes>
                </AuthProvider>
              </RegionProvider>
            </CountersProvider>
          </CartToastProvider>
        </CartPanelProvider>
      </MemoryRouter>
    </SWRConfig>,
  );
}

async function fillGuest() {
  await screen.findByText('Стол');
  fireEvent.change(screen.getByLabelText('Фамилия'), { target: { value: 'Иванов' } });
  fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Иван' } });
  fireEvent.change(screen.getByLabelText('Отчество'), { target: { value: 'Петрович' } });
  fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+79001234567' } });
  fireEvent.change(screen.getByLabelText('Почта'), { target: { value: 'buyer@example.test' } });
  fireEvent.change(screen.getByRole('combobox', { name: 'Город' }), { target: { value: 'Лип' } });
  fireEvent.click(await screen.findByRole('option', { name: 'г. Липецк' }));
  fireEvent.focus(screen.getByRole('combobox', { name: 'Улица' }));
  fireEvent.click(await screen.findByRole('option', { name: 'ул. Советская' }));
  fireEvent.focus(screen.getByRole('combobox', { name: 'Дом / корпус / строение' }));
  fireEvent.click(await screen.findByRole('option', { name: 'д. 64' }));
}

it('adds +7 after the second digit without interrupting typing and preserves editing and deletion', async () => {
  renderCheckout();
  await screen.findByText('Стол');
  const phone = screen.getByLabelText('Телефон') as HTMLInputElement;
  fireEvent.focus(phone);
  fireEvent.input(phone, { target: { value: '9', selectionStart: 1 }, inputType: 'insertText' });
  expect(phone).toHaveValue('9');
  fireEvent.input(phone, { target: { value: '99', selectionStart: 2 }, inputType: 'insertText' });
  expect(phone).toHaveValue('+799');
  expect(phone.selectionStart).toBe(4);
  for (const digit of '96565455') {
    fireEvent.input(phone, {
      target: { value: phone.value + digit, selectionStart: phone.value.length + 1 },
      inputType: 'insertText',
    });
  }
  expect(phone).toHaveValue('+79996565455');
  fireEvent.input(phone, {
    target: { value: '+79986565455', selectionStart: 5 },
    inputType: 'insertReplacementText',
  });
  expect(phone.selectionStart).toBe(5);
  fireEvent.input(phone, {
    target: { value: '+7998656545', selectionStart: 11 },
    inputType: 'deleteContentBackward',
  });
  fireEvent.blur(phone);
  expect(phone).toHaveValue('+7998656545');
  expect(phone).toHaveAttribute('aria-invalid', 'true');
  fireEvent.input(phone, { target: { value: '' }, inputType: 'deleteContentBackward' });
  fireEvent.blur(phone);
  expect(phone).toHaveValue('');
  fireEvent.input(phone, { target: { value: '8', selectionStart: 1 }, inputType: 'insertText' });
  expect(phone).toHaveValue('8');
  fireEvent.input(phone, { target: { value: '89', selectionStart: 2 }, inputType: 'insertText' });
  expect(phone).toHaveValue('+79');
  expect(phone.selectionStart).toBe(3);
  for (const digit of '995655566') {
    fireEvent.input(phone, {
      target: { value: phone.value + digit, selectionStart: phone.value.length + 1 },
      inputType: 'insertText',
    });
  }
  expect(phone).toHaveValue('+79995655566');
});

it.each([
  ['9996565455', '+79996565455'],
  ['+79996565455', '+79996565455'],
  ['89995655566', '+79995655566'],
  ['8 (999) 565-55-66', '+79995655566'],
  ['7996565455', '+77996565455'],
])(
  'normalizes pasted or replaced phone %s immediately and sends %s to the server',
  async (entered, expected) => {
    vi.mocked(api.orders.create).mockResolvedValue({ order: { id: 99, can_pay: false } } as Awaited<
      ReturnType<typeof api.orders.create>
    >);
    const { container } = renderCheckout();
    await fillGuest();
    const phone = screen.getByLabelText('Телефон');
    fireEvent.change(phone, { target: { value: entered } });
    expect(phone).toHaveValue(expected);
    fireEvent.blur(phone);
    expect(phone).toHaveValue(expected);
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled(),
    );
    fireEvent.submit(container.querySelector('form')!);
    expect(await screen.findByText('Заказ принят')).toBeInTheDocument();
    expect(api.orders.create).toHaveBeenCalledWith(
      expect.objectContaining({ contact_phone: expected }),
    );
  },
);

it('rejects invalid contacts beside the fields and submits after they are corrected', async () => {
  vi.mocked(api.orders.create).mockResolvedValue({ order: { id: 99, can_pay: false } } as Awaited<
    ReturnType<typeof api.orders.create>
  >);
  const { container } = renderCheckout();
  await fillGuest();
  await waitFor(() => expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled());
  const phone = screen.getByLabelText('Телефон');
  const email = screen.getByLabelText('Почта');
  fireEvent.change(phone, { target: { value: 'буквы' } });
  expect(phone).toHaveValue('');
  fireEvent.blur(phone);
  expect(phone).toHaveAccessibleDescription(
    'Введите российский номер телефона: 10 цифр или 11 с 7/8.',
  );
  fireEvent.change(email, { target: { value: 'непочта' } });
  fireEvent.blur(email);
  expect(email).toHaveAccessibleDescription('Введите почту в формате name@example.ru.');
  fireEvent.submit(container.querySelector('form')!);
  expect(api.orders.create).not.toHaveBeenCalled();
  fireEvent.change(phone, { target: { value: '+799912345678901' } });
  fireEvent.change(email, { target: { value: 'buyer@example.test' } });
  fireEvent.submit(container.querySelector('form')!);
  expect(api.orders.create).not.toHaveBeenCalled();
  fireEvent.change(phone, { target: { value: '8 (900) 123-45-67' } });
  expect(phone).not.toHaveAttribute('aria-invalid');
  expect(email).not.toHaveAttribute('aria-invalid');
  fireEvent.submit(container.querySelector('form')!);
  expect(await screen.findByText('Заказ принят')).toBeInTheDocument();
});

it('guest delivery submits the chosen directory building, live SWR subtotal and details once during a delayed order', async () => {
  const calculation = deferred<WarehouseQuote>();
  const order = deferred<Awaited<ReturnType<typeof api.orders.create>>>();
  vi.mocked(api.shipping.quote).mockReturnValueOnce(calculation.promise);
  vi.mocked(api.orders.create).mockReturnValueOnce(order.promise);
  const { container } = renderCheckout();
  await fillGuest();
  expect(screen.queryByRole('checkbox', { name: /Принимаю/ })).not.toBeInTheDocument();
  expect(screen.queryByText(/60 минут в обработке/)).not.toBeInTheDocument();
  await screen.findByText('Рассчитываем доставку…');
  expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeDisabled();
  fireEvent.change(screen.getByLabelText('Квартира / помещение'), { target: { value: '17' } });
  fireEvent.change(screen.getByLabelText('Подъезд'), { target: { value: '2' } });
  await act(async () => calculation.resolve(quote));
  await waitFor(() => expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled());
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/);
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  fireEvent.submit(container.querySelector('form')!);
  fireEvent.submit(container.querySelector('form')!);
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const payload = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(payload).toMatchObject({
    contact_name: 'Иванов Иван Петрович',
    contact_phone: '+79001234567',
    contact_email: 'buyer@example.test',
    payment_method: 'cash',
    delivery_type: 'delivery',
    directory_external_id: 'house-guid',
    warehouse_id: 4,
    expected_subtotal: 1000,
    delivery_details: { apartment: '17', entrance: '2' },
    accept_offer: true,
    accept_personal_data: true,
  });
  expect(payload).not.toHaveProperty('address_id');
  expect(payload).not.toHaveProperty('shipping_location_id');
  expect(payload).not.toHaveProperty('delivery_cost');
  expect(payload).not.toHaveProperty('items');
  expect(api.addresses.create).not.toHaveBeenCalled();
  expect(api.cart.get).toHaveBeenCalled();
  await act(async () =>
    order.resolve({ order: { id: 99, can_pay: false } } as Awaited<
      ReturnType<typeof api.orders.create>
    >),
  );
  expect(await screen.findByText('Заказ принят')).toBeInTheDocument();
});

it('switching to pickup while a real session read is pending ignores its stale delivery response', async () => {
  const calculation = deferred<WarehouseQuote>();
  const order = deferred<Awaited<ReturnType<typeof api.orders.create>>>();
  vi.mocked(api.shipping.quote).mockReturnValueOnce(calculation.promise);
  vi.mocked(api.orders.create).mockReturnValueOnce(order.promise);
  renderCheckout();
  await fillGuest();
  await screen.findByText('Рассчитываем доставку…');
  fireEvent.click(screen.getByRole('radio', { name: 'Самовывоз' }));
  fireEvent.click(screen.getByRole('combobox', { name: 'Точка самовывоза' }));
  fireEvent.click(await screen.findByRole('option', { name: 'Липецк — Адрес магазина' }));
  expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled();
  fireEvent.submit(screen.getByRole('button', { name: 'Оформить заказ' }).closest('form')!);
  // Запись сессии ждёт ещё не завершённое чтение расчёта.
  expect(api.orders.create).not.toHaveBeenCalled();
  await act(async () =>
    calculation.resolve({ available: false, subtotal: 1, options: [], selected: null }),
  );
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const payload = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(payload).toMatchObject({
    delivery_type: 'pickup',
    warehouse_id: 4,
    store_id: 2,
    expected_subtotal: 1000,
  });
  expect(payload).not.toHaveProperty('directory_external_id');
  expect(payload).not.toHaveProperty('address_id');
  expect(payload).not.toHaveProperty('delivery_details');
  expect(
    screen.queryByText('Доставка по выбранному адресу недоступна для всего заказа.'),
  ).not.toBeInTheDocument();
  await act(async () =>
    order.resolve({ order: { id: 100, can_pay: false } } as Awaited<
      ReturnType<typeof api.orders.create>
    >),
  );
  expect(await screen.findByText('Заказ принят')).toBeInTheDocument();
});
it('shows server discounts in checkout without changing the payable subtotal', async () => {
  vi.mocked(api.cart.get).mockResolvedValue({
    ...cart,
    old_subtotal: 1500,
    discount_total: 500,
    items: [
      {
        ...cart.items[0],
        old_price: 1500,
        old_total: 1500,
        discount_amount: 500,
        discount_percent: 33,
      },
    ],
  });
  renderCheckout();
  await fillGuest();
  await waitFor(() =>
    expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent(/1\s500/),
  );
  expect(screen.getByText('Скидка').parentElement).toHaveTextContent('−500 ₽');
  expect(screen.getByText('Стоимость товаров').parentElement).toHaveTextContent(/1\s500/);
});
