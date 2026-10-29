import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import Checkout from './warehouse-page';
import { api } from '../../lib/api';

const { navigate, reload, cart } = vi.hoisted(() => ({ navigate: vi.fn(), reload: vi.fn(), cart: { subtotal: 1000, items: [{ product_id: 17, quantity: 1 }] } }));
vi.mock('react-router-dom', async () => ({ ...await vi.importActual('react-router-dom'), useNavigate: () => navigate }));
vi.mock('../../hooks/useAuth', () => ({ useAuth: () => ({ user: null }) }));
vi.mock('../../hooks/useCart', () => ({ useCart: () => ({ cart, reloadCart: reload }) }));
vi.mock('../../components/CheckoutAddress', () => ({ default: ({ onChange }: { onChange: (id: string) => void }) => <button type="button" onClick={() => onChange('house-guid')}>Выбрать дом</button> }));
vi.mock('../../lib/api', () => ({ api: { paymentMethods: { list: vi.fn() }, orders: { create: vi.fn(), prepare: vi.fn() }, shipping: { quote: vi.fn(), pickupOptions: vi.fn() } } }));

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(api.paymentMethods.list).mockResolvedValue({ data: [{ id: 1, code: 'cash', name: 'Наличные', is_active: true, sort_order: 0 }] });
  vi.mocked(api.shipping.pickupOptions).mockResolvedValue({ pickup: [{ warehouse_id: 4, store_id: 2, name: 'Липецк', address: 'Адрес магазина' }] });
  vi.mocked(api.shipping.quote).mockResolvedValue({ available: true, subtotal: 1000, options: [], selected: { warehouse_id: 4, warehouse_name: 'Центральный', delivery_cost: 500, assembly_cost: 0, total_fulfillment_cost: 500,
    service_options: { assembly: { available: false, required: false, price: null }, handling: [], additional: [] } } });
  vi.mocked(api.orders.create).mockResolvedValue({ order: { id: 99, can_pay: false } as never });
});
afterEach(cleanup);

it('shows digital ruble as coming soon and chooses an available payment method', async () => {
  vi.mocked(api.paymentMethods.list).mockResolvedValue({ data: [
    { id: 2, code: 'digital_ruble', name: 'Цифровой рубль', is_active: false, is_coming_soon: true, selectable: false, sort_order: 0 },
    { id: 1, code: 'card', name: 'Банковская карта', is_active: true, selectable: true, sort_order: 1 },
  ] });
  render(<MemoryRouter><Checkout /></MemoryRouter>);
  const option = await screen.findByRole('option', { name: 'Цифровой рубль — Скоро' });
  expect((option as HTMLOptionElement).disabled).toBe(true);
  expect((screen.getByLabelText('Способ оплаты') as HTMLSelectElement).value).toBe('card');
});

it('cannot submit when only coming soon methods exist, including a forged selection', async () => {
  vi.mocked(api.paymentMethods.list).mockResolvedValue({ data: [
    { id: 2, code: 'digital_ruble', name: 'Цифровой рубль', is_active: false, is_coming_soon: true, selectable: false, sort_order: 0 },
  ] });
  const { container } = render(<MemoryRouter><Checkout /></MemoryRouter>);
  await screen.findByRole('option', { name: 'Цифровой рубль — Скоро' });
  fireEvent.click(screen.getByRole('checkbox'));
  fireEvent.change(screen.getByLabelText('Способ оплаты'), { target: { value: 'digital_ruble' } });
  expect((screen.getByRole('button', { name: 'Оформить заказ' }) as HTMLButtonElement).disabled).toBe(true);
  fireEvent.submit(container.querySelector('form')!);
  expect(api.orders.create).not.toHaveBeenCalled();
});

async function contacts() {
  fireEvent.change(screen.getByLabelText('Имя'), { target: { value: 'Покупатель' } });
  fireEvent.change(screen.getByLabelText('Телефон'), { target: { value: '+79001234567' } });
  fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'buyer@example.test' } });
  fireEvent.click(screen.getByRole('checkbox'));
  await waitFor(() => expect(screen.getByRole('option', { name: 'Наличные' })).toBeTruthy());
}
it('submits the building and selected warehouse, without legacy shipping fields or client costs', async () => {
  render(<MemoryRouter><Checkout /></MemoryRouter>);
  await contacts();
  fireEvent.click(screen.getByText('Выбрать дом'));
  await waitFor(() => expect(screen.getByText('Итого: 1500 ₽')).toBeTruthy());
  fireEvent.click(screen.getByRole('button', { name: 'Оформить заказ' }));
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(body).toMatchObject({ directory_external_id: 'house-guid', warehouse_id: 4, expected_subtotal: 1000 });
  expect(body).not.toHaveProperty('shipping_location_id');
  expect(body).not.toHaveProperty('delivery_cost');
  expect(body).not.toHaveProperty('items');
  await waitFor(() => expect(navigate).toHaveBeenCalledWith('/orders/99'));
});
it('submits pickup store and warehouse without a customer delivery address', async () => {
  render(<MemoryRouter><Checkout /></MemoryRouter>);
  await contacts();
  fireEvent.change(screen.getByLabelText('Способ получения'), { target: { value: 'pickup' } });
  await waitFor(() => expect(screen.getByRole('option', { name: 'Липецк — Адрес магазина' })).toBeTruthy());
  fireEvent.change(screen.getByLabelText('Точка самовывоза'), { target: { value: '4:2' } });
  fireEvent.click(screen.getByRole('button', { name: 'Оформить заказ' }));
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  const body = vi.mocked(api.orders.create).mock.calls[0][0];
  expect(body).toMatchObject({ delivery_type: 'pickup', warehouse_id: 4, store_id: 2 });
  expect(body).not.toHaveProperty('directory_external_id');
});
it('does not silently resubmit after a changed price', async () => {
  vi.mocked(api.orders.create).mockRejectedValueOnce({ status: 409 });
  render(<MemoryRouter><Checkout /></MemoryRouter>);
  await contacts(); fireEvent.click(screen.getByText('Выбрать дом'));
  await screen.findByText('Итого: 1500 ₽');
  fireEvent.click(screen.getByRole('button', { name: 'Оформить заказ' }));
  await waitFor(() => expect(reload).toHaveBeenCalled());
  expect(api.orders.create).toHaveBeenCalledTimes(1);
});
it('prefills the one-click form and sends its comment with the direct item', async () => {
  vi.mocked(api.orders.prepare).mockResolvedValue({ items: [{ product_id: 42, quantity: 2 }], subtotal: 2000 });
  render(<MemoryRouter initialEntries={[{ pathname: '/checkout', search: '?product_id=42&quantity=2',
    state: { oneClick: { name: 'Иванов Иван', phone: '+7 (912) 345-67-89', comment: 'Позвоните после 18' } } }]}><Checkout /></MemoryRouter>);
  expect(screen.getByLabelText('Имя')).toHaveValue('Иванов Иван');
  expect(screen.getByLabelText('Телефон')).toHaveValue('+7 (912) 345-67-89');
  expect(screen.getByLabelText('Комментарий к заказу')).toHaveValue('Позвоните после 18');
  fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'buyer@example.test' } });
  fireEvent.click(screen.getByRole('checkbox'));
  await waitFor(() => expect(screen.getByRole('option', { name: 'Наличные' })).toBeTruthy());
  fireEvent.click(screen.getByText('Выбрать дом'));
  await screen.findByText('Итого: 1500 ₽');
  fireEvent.click(screen.getByRole('button', { name: 'Оформить заказ' }));
  await waitFor(() => expect(api.orders.create).toHaveBeenCalledTimes(1));
  expect(vi.mocked(api.orders.create).mock.calls[0][0]).toMatchObject({
    items: [{ product_id: 42, quantity: 2 }], comment: 'Позвоните после 18',
    contact_name: 'Иванов Иван', accept_offer: true, accept_personal_data: true,
  });
});
