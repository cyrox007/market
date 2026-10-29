import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import ProductCard from './ProductCard';
import type { Product } from '../../lib/api';

vi.mock('../../hooks/usePrefetchProduct', () => ({ usePrefetchProduct: () => () => {} }));

const product = {
  id: 7,
  name: 'Стол',
  slug: 'stol',
  price: 100,
  in_stock: true,
  stock: 10,
  is_variable: false,
  is_variant: false,
  first_available_variant_id: null,
} as unknown as Product;

describe('ProductCard', () => {
  it('passes every quick click on plus to the page, none is swallowed', () => {
    // Сервер ещё не ответил ни на один клик
    const onIncreaseCart = vi.fn(() => new Promise<void>(() => {}));
    render(
      <MemoryRouter>
        <ProductCard product={product} cartQuantity={1} onAddToCart={vi.fn()} onIncreaseCart={onIncreaseCart} />
      </MemoryRouter>,
    );

    const plus = screen.getByRole('button', { name: 'Увеличить количество' });
    for (let i = 0; i < 5; i += 1) fireEvent.click(plus);

    expect(onIncreaseCart).toHaveBeenCalledTimes(5);
  });

  const cartButton = (overrides: Partial<Product>) => {
    render(
      <MemoryRouter>
        <ProductCard product={{ ...product, ...overrides }} onAddToCart={vi.fn()} />
      </MemoryRouter>,
    );
    return screen.getByRole('button', { name: 'В корзину' });
  };

  it('«в корзину» доступна, когда остатка нет, но бэк разрешает заказ', () => {
    expect(
      cartButton({ in_stock: false, stock: 0, backorder: false, available_to_order: true }),
    ).toBeEnabled();
  });

  it('«в корзину» недоступна при флаге «под заказ», если бэк заказ не разрешает', () => {
    expect(
      cartButton({ in_stock: false, stock: 0, backorder: true, available_to_order: false }),
    ).toBeDisabled();
  });
});
