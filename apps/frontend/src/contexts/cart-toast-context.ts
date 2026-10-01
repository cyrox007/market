import { createContext, useContext } from 'react';

export type ToastType = 'success' | 'error';

export interface CartToastContextType {
  showCartToast: (message: string, type?: ToastType) => void;
}

export const CartToastContext = createContext<CartToastContextType | undefined>(undefined);

export function useCartToast() {
  const ctx = useContext(CartToastContext);
  if (!ctx) {
    return { showCartToast: () => {} };
  }
  return ctx;
}
