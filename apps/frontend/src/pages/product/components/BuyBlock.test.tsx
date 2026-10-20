import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import type { ProductDetail, ProductVariant } from '../../../lib/api';
import BuyBlock, { type BuyBlockCart } from './BuyBlock';

const product = {
  id: 1,
  slug: 'kuhnya',
  name: 'Кухонный гарнитур «Акапелла»',
  sku: '08-169420',
  price: 184150,
  old_price: 194150,
  rating: 4.6,
  reviews_count: 12,
  is_variable: false,
  in_stock: true,
  stock: 12,
  stock_known: true,
} as unknown as unknown as ProductDetail;

const cart = (over: Partial<BuyBlockCart> = {}): BuyBlockCart => ({
  quantity: 0,
  isBusy: false,
  error: null,
  add: vi.fn(),
  increment: vi.fn(),
  decrement: vi.fn(),
  ...over,
});

function renderBlock(
  props: {
    product?: ProductDetail;
    selectedVariant?: ProductVariant | null;
    cart?: BuyBlockCart;
    onGoToReviews?: () => void;
    onOneClick?: () => void;
  } = {},
) {
  return render(
    <MemoryRouter>
      <BuyBlock
        product={props.product ?? product}
        selectedVariant={props.selectedVariant ?? null}
        attributes={[]}
        onSelectValue={() => {}}
        city="Липецк"
        cart={props.cart ?? cart()}
        onGoToReviews={props.onGoToReviews ?? (() => {})}
        onOneClick={props.onOneClick ?? (() => {})}
      />
    </MemoryRouter>,
  );
}

describe('блок покупки', () => {
  it('название и артикул; артикул копируется с подтверждением', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });
    renderBlock();
    expect(
      screen.getByRole('heading', { name: 'Кухонный гарнитур «Акапелла»' }),
    ).toBeInTheDocument();
    expect(screen.getByText('Арт.: 08-169420')).toBeInTheDocument();
    // Подсказка у кнопки, а не текст в строке — строка не сдвигается
    expect(screen.getByText('Скопировать')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Скопировать артикул' }));
    expect(writeText).toHaveBeenCalledWith('08-169420');
    expect(await screen.findByText('Скопировано')).toBeInTheDocument();
    expect(screen.queryByText('Скопировать')).not.toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Артикул скопирован');
  });

  it('рейтинг и число отзывов ведут к отзывам', () => {
    const onGoToReviews = vi.fn();
    renderBlock({ onGoToReviews });
    const link = screen.getByRole('button', { name: /4,6.*12 отзывов/ });
    fireEvent.click(link);
    expect(onGoToReviews).toHaveBeenCalled();
  });

  it('отзывов нет — «Нет отзывов»', () => {
    renderBlock({ product: { ...product, rating: 0, reviews_count: 0 } });
    expect(screen.getByRole('button', { name: /Нет отзывов/ })).toBeInTheDocument();
  });

  it('в наличии — плашки «В наличии» и «Остаток в г. Липецк: 12 шт.»', () => {
    renderBlock();
    expect(screen.getByText('В наличии')).toBeInTheDocument();
    expect(screen.getByText('Остаток в г. Липецк: 12 шт.')).toBeInTheDocument();
  });

  it('остаток неизвестен — без числа', () => {
    renderBlock({
      product: { ...product, stock: null, stock_known: false } as unknown as ProductDetail,
    });
    expect(screen.getByText('В наличии')).toBeInTheDocument();
    expect(screen.queryByText(/Остаток в/)).not.toBeInTheDocument();
  });

  it('под заказ — купить можно', () => {
    const c = cart();
    renderBlock({
      product: {
        ...product,
        in_stock: false,
        stock: 0,
        available_to_order: true,
      } as unknown as ProductDetail,
      cart: c,
    });
    expect(screen.getByText('Под заказ')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Добавить в корзину' }));
    expect(c.add).toHaveBeenCalled();
  });

  it('нет в наличии — корзина неактивна, «Купить в 1 клик» работает', () => {
    const onOneClick = vi.fn();
    renderBlock({
      product: {
        ...product,
        in_stock: false,
        stock: 0,
        available_to_order: false,
      } as unknown as ProductDetail,
      onOneClick,
    });
    expect(screen.getByText('Нет в наличии')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Добавить в корзину' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Купить в 1 клик' }));
    expect(onOneClick).toHaveBeenCalled();
  });

  it('цена, старая цена и скидка', () => {
    renderBlock();
    expect(screen.getByText(/^184\s150 ₽$/)).toBeInTheDocument();
    expect(screen.getByText(/^194\s150 ₽$/)).toBeInTheDocument();
    expect(screen.getByText('-5 %')).toBeInTheDocument();
  });

  it('без старой цены — без скидки', () => {
    renderBlock({ product: { ...product, old_price: null } });
    expect(screen.queryByText(/%$/)).not.toBeInTheDocument();
  });

  it('у варианта — его цена, артикул и наличие', () => {
    const variant = {
      id: 7,
      sku: 'VAR-7',
      price: 1000,
      old_price: null,
      in_stock: false,
      stock: 0,
      available_to_order: false,
    } as unknown as ProductVariant;
    renderBlock({ product: { ...product, is_variable: true }, selectedVariant: variant });
    expect(screen.getByText('Арт.: VAR-7')).toBeInTheDocument();
    expect(screen.getByText(/^1\s000 ₽$/)).toBeInTheDocument();
    expect(screen.getByText('Нет в наличии')).toBeInTheDocument();
  });

  it('в корзине — счётчик и «Перейти в корзину»; плюс упирается в остаток', () => {
    const c = cart({ quantity: 12 });
    renderBlock({ cart: c });
    expect(screen.queryByRole('button', { name: 'Добавить в корзину' })).not.toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Перейти в корзину/ })).toHaveAttribute(
      'href',
      '/cart',
    );
    expect(screen.getByRole('button', { name: 'Увеличить количество' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Уменьшить количество' }));
    expect(c.decrement).toHaveBeenCalled();
  });

  it('в корзине 1 — минус доступен: уберёт товар и вернёт кнопку', () => {
    const c = cart({ quantity: 1 });
    renderBlock({ cart: c });
    const minus = screen.getByRole('button', { name: 'Уменьшить количество' });
    expect(minus).toBeEnabled();
    fireEvent.click(minus);
    expect(c.decrement).toHaveBeenCalled();
  });

  it('под заказ — плюс без предела', () => {
    renderBlock({
      product: {
        ...product,
        in_stock: false,
        stock: 0,
        available_to_order: true,
      } as unknown as ProductDetail,
      cart: cart({ quantity: 40 }),
    });
    expect(screen.getByRole('button', { name: 'Увеличить количество' })).toBeEnabled();
  });

  it('ошибка корзины видна', () => {
    renderBlock({ cart: cart({ error: 'Товар недоступен для заказа' }) });
    expect(screen.getByRole('alert')).toHaveTextContent('Товар недоступен для заказа');
  });
});
