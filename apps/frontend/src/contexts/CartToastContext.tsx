import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import Toast from '../components/ui/Toast';

type ToastType = 'success' | 'error';

interface CartToastContextType {
  showCartToast: (message: string, type?: ToastType) => void;
}

const CartToastContext = createContext<CartToastContextType | undefined>(undefined);

export function CartToastProvider({ children }: { children: ReactNode }) {
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
      />
    </CartToastContext.Provider>
  );
}

export function useCartToast() {
  const ctx = useContext(CartToastContext);
  if (!ctx) {
    return { showCartToast: () => {} };
  }
  return ctx;
}
