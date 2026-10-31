import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { CartPanelContext } from './cart-panel-context';

export function CartPanelProvider({ children }: { children: ReactNode }) {
  const [isOpen, setOpen] = useState(false);
  const openCart = useCallback(() => setOpen(true), []);
  const closeCart = useCallback(() => setOpen(false), []);
  const value = useMemo(() => ({ isOpen, openCart, closeCart }), [isOpen, openCart, closeCart]);
  return <CartPanelContext.Provider value={value}>{children}</CartPanelContext.Provider>;
}
