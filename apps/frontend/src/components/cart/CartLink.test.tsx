import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { CartPanelProvider } from '../../contexts/CartPanelContext';
import { useCartPanel } from '../../contexts/cart-panel-context';
import CartLink from './CartLink';
import UserActions from '../layout/Header/menu/UserActions';
import BottomBar from '../layout/BottomBar/BottomBar';

function State() {
  const panel = useCartPanel();
  return <output>{`${useLocation().pathname}:${panel.isOpen}`}</output>;
}
describe('точки открытия корзины', () => {
  it.each(['Ссылка', 'Шапка', 'Нижнее меню'])('%s открывает панель без смены адреса', (kind) => {
    render(
      <MemoryRouter initialEntries={['/catalog']}>
        <CartPanelProvider>
          {kind === 'Ссылка' ? (
            <CartLink>Перейти в корзину</CartLink>
          ) : kind === 'Шапка' ? (
            <UserActions />
          ) : (
            <BottomBar />
          )}
          <State />
        </CartPanelProvider>
      </MemoryRouter>,
    );
    fireEvent.click(
      screen.getByRole('link', { name: kind === 'Ссылка' ? 'Перейти в корзину' : 'Корзина' }),
    );
    expect(screen.getByRole('status')).toHaveTextContent('/catalog:true');
  });
});
