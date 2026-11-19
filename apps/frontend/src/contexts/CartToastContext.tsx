import { useCallback, useMemo, useState, type ReactNode } from 'react';
import Toast from '../components/ui/Toast';
import { CartToastContext, type ToastType } from './cart-toast-context';
import { useCartPanel } from './cart-panel-context';

export function CartToastProvider({ children }: { children: ReactNode }) {
  const { openCart } = useCartPanel();
  const [toast, setToast] = useState<{ message: string; type: ToastType } | null>(null);

  const showCartToast = useCallback((message: string, type: ToastType = 'success') => {
    setToast({ message, type });
  }, []);

  const value = useMemo(() => ({ showCartToast }), [showCartToast]);

  return (
    <CartToastContext.Provider value={value}>
      {children}
      <Toast
        message={toast?.message ?? ''}
        type={toast?.type ?? 'success'}
        isVisible={!!toast}
        onClose={() => setToast(null)}
        duration={3500}
        action={
          toast?.type === 'success' && /корзин/i.test(toast.message)
            ? {
                label: 'Перейти в корзину',
                onClick: () => {
                  setToast(null);
                  openCart();
                },
              }
            : undefined
        }
      />
    </CartToastContext.Provider>
  );
}
