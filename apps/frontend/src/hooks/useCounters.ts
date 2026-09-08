import { useContext } from 'react';
import { CountersContext } from '../contexts/CountersContext';

// Значения по умолчанию для случая, когда контекст не инициализирован
const defaultCountersValue = {
  cartCount: 0,
  wishlistCount: 0,
  compareCount: 0,
  isLoading: true,
  setCartCount: () => {},
  bumpCartCount: () => {},
  refreshCartCount: async () => {
    throw new Error('CountersProvider not initialized');
  },
  refreshWishlistCount: async () => {
    throw new Error('CountersProvider not initialized');
  },
  refreshCompareCount: async () => {
    throw new Error('CountersProvider not initialized');
  },
  refreshAllCounts: async () => {
    throw new Error('CountersProvider not initialized');
  },
};

export function useCounters() {
  const context = useContext(CountersContext);

  if (!context) {
    return defaultCountersValue;
  }

  return context;
}
