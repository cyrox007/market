import { createContext, useCallback, useContext } from 'react';
import { useNavigate } from 'react-router-dom';

export interface CartPanelState {
  isOpen: boolean;
  openCart: () => void;
  closeCart: () => void;
}
export const CartPanelContext = createContext<CartPanelState | null>(null);

/** В песочнице без провайдера ссылка остаётся обычной навигацией. */
export function useCartPanel(): CartPanelState {
  const context = useContext(CartPanelContext);
  const navigate = useNavigate();
  const fallbackOpen = useCallback(() => navigate('/cart'), [navigate]);
  return context ?? { isOpen: false, openCart: fallbackOpen, closeCart: () => {} };
}
