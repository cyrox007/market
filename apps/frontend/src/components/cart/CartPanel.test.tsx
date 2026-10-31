import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { BrowserRouter, MemoryRouter, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CartPanel from './CartPanel';
import { CartPanelProvider } from '../../contexts/CartPanelContext';
import { useCartPanel } from '../../contexts/cart-panel-context';
import type { Cart, CartItem } from '../../lib/api';

const mocks = vi.hoisted(() => ({
  cart: null as Cart | null,
  isLoading: false,
  error: null as string | null,
  setCartQuantity: vi.fn(),
  updateQuantity: vi.fn(),
  removeFromCart: vi.fn(),
  clearCart: vi.fn(),
  reloadCart: vi.fn(),
  toggleWishlist: vi.fn(),
  showCartToast: vi.fn(),
}));
vi.mock('../../hooks/useCart', () => ({ useCart: () => mocks }));
vi.mock('../../hooks/useWishlistAndCompare', () => ({
  useWishlistAndCompare: () => ({ wishlistProductIds: [], toggleWishlist: mocks.toggleWishlist }),
}));
vi.mock('../../hooks/usePrefetchProduct', () => ({ usePrefetchProduct: () => vi.fn() }));
vi.mock('../../hooks/useMediaQuery', () => ({ useMediaQuery: () => false }));
vi.mock('../../contexts/cart-toast-context', () => ({
  useCartToast: () => ({ showCartToast: mocks.showCartToast }),
}));

const line = (overrides: Partial<CartItem> = {}): CartItem => ({
  id: 1,
  product_id: 12,
  name: 'Шкаф',
  slug: 'shkaf',
  price: 100,
  total: 100,
  quantity: 1,
  image: null,
  sku: null,
  stock: 2,
  ...overrides,
});
const fill = (...items: CartItem[]) => {
  mocks.cart = { items, subtotal: 100, item_count: items.length, is_empty: !items.length };
};
function Harness() {
  const { openCart } = useCartPanel();
  return (
    <>
      <button onClick={openCart}>Открыть корзину</button>
      <output>{useLocation().pathname}</output>
      <CartPanel />
    </>
  );
}
const App = () => (
  <MemoryRouter initialEntries={['/catalog']}>
    <CartPanelProvider>
      <Harness />
    </CartPanelProvider>
  </MemoryRouter>
);
const open = () => fireEvent.click(screen.getByText('Открыть корзину'));

beforeEach(() => {
  vi.clearAllMocks();
  fill();
  mocks.isLoading = false;
  mocks.error = null;
  mocks.clearCart.mockResolvedValue(undefined);
});

describe('панель корзины', () => {
  it('переход из панели заменяет её временную запись в настоящей истории браузера', async () => {
    window.history.replaceState({}, '', '/catalog');
    fill(line());
    const before = window.history.length;
    render(
      <BrowserRouter>
        <CartPanelProvider>
          <Harness />
        </CartPanelProvider>
      </BrowserRouter>,
    );
    open();
    expect(window.history.length).toBe(before + 1);
    fireEvent.click(screen.getByRole('button', { name: 'Перейти к оформлению' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(window.history.length).toBe(before + 1);
    expect(window.location.pathname).toBe('/checkout');
    await act(async () => {
      window.history.back();
    });
    await waitFor(() => expect(window.location.pathname).toBe('/catalog'));
  });
  it('открывается поверх текущей страницы, пустая корзина не позволяет оформить заказ', () => {
    render(<App />);
    open();
    expect(screen.getByRole('dialog')).toHaveTextContent('В корзине пока пусто');
    expect(screen.getByRole('button', { name: 'Перейти к оформлению' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Очистить' })).not.toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('/catalog');
  });
  it('показывает четыре скелетона только без первых данных', () => {
    mocks.cart = null;
    mocks.isLoading = true;
    const view = render(<App />);
    open();
    expect(screen.getAllByTestId('cart-skeleton')).toHaveLength(4);
    fill(line());
    view.rerender(<App />);
    expect(screen.queryByTestId('cart-skeleton')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Шкаф' })).toBeInTheDocument();
  });
  it('считает штуки и сохраняет предел количества, минус на единице отключён', () => {
    fill(line(), line({ id: 2, product_id: 13, name: 'Стол', quantity: 3, total: 300, stock: 3 }));
    render(<App />);
    open();
    expect(screen.getByRole('dialog')).toHaveAccessibleName('Корзина / 4 товара');
    const row = screen.getByRole('article', { name: 'Шкаф' });
    expect(within(row).getByRole('button', { name: 'Уменьшить количество' })).toBeDisabled();
    fireEvent.click(within(row).getByRole('button', { name: 'Увеличить количество' }));
    expect(mocks.setCartQuantity).toHaveBeenCalledWith(12, 2, { max: 2 });
    expect(
      within(screen.getByRole('article', { name: 'Стол' })).getByRole('button', {
        name: 'Увеличить количество',
      }),
    ).toBeDisabled();
  });
  it('удаляет без подтверждения и переключает избранное', () => {
    fill(line());
    render(<App />);
    open();
    fireEvent.click(screen.getByRole('button', { name: 'Добавить в избранное' }));
    expect(mocks.toggleWishlist).toHaveBeenCalledWith(12);
    fireEvent.click(screen.getByRole('button', { name: 'Удалить товар' }));
    expect(mocks.setCartQuantity).toHaveBeenCalledWith(12, 0);
  });
  it('различает две строки одного SKU с обычной и закрытой ценой', async () => {
    fill(line(), line({ id: 2, price_source: 'private_link' }));
    mocks.removeFromCart.mockResolvedValue(undefined);
    mocks.updateQuantity.mockResolvedValue(undefined);
    render(<App />);
    open();
    const rows = screen.getAllByRole('article', { name: 'Шкаф' });
    fireEvent.click(within(rows[1]).getByRole('button', { name: 'Увеличить количество' }));
    await waitFor(() => expect(mocks.updateQuantity).toHaveBeenCalledWith(2, 2));
    fireEvent.click(within(rows[1]).getByRole('button', { name: 'Удалить товар' }));
    await waitFor(() => expect(mocks.removeFromCart).toHaveBeenCalledWith(2));
    expect(mocks.setCartQuantity).not.toHaveBeenCalled();
  });
  it('исключает недоступный товар из итогов и объясняет блокировку', () => {
    fill(line({ stock: 0 }));
    render(<App />);
    open();
    expect(screen.getByText('Нет в наличии')).toBeInTheDocument();
    expect(screen.getByText('Удалите недоступные товары')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Перейти к оформлению' })).toBeDisabled();
    expect(screen.getByTestId('cart-total')).toHaveTextContent('0 ₽');
  });
  it('очищает только после подтверждения', async () => {
    fill(line());
    render(<App />);
    open();
    fireEvent.click(screen.getByRole('button', { name: 'Очистить' }));
    expect(mocks.clearCart).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Отмена' }));
    expect(mocks.clearCart).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Очистить' }));
    fireEvent.click(screen.getByRole('button', { name: 'Да, очистить' }));
    await waitFor(() => expect(mocks.clearCart).toHaveBeenCalledTimes(1));
  });
  it('переходит к оформлению и закрывает панель', async () => {
    fill(line());
    render(<App />);
    open();
    fireEvent.click(screen.getByRole('button', { name: 'Перейти к оформлению' }));
    expect(screen.getByRole('status')).toHaveTextContent('/checkout');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});
