import { createContext, useState, useEffect, useCallback, ReactNode, useMemo, useRef } from 'react';
import { api } from '../lib/api';

interface CountersContextType {
  cartCount: number;
  wishlistCount: number;
  compareCount: number;
  isLoading: boolean;
  setCartCount: (count: number) => void;
  bumpCartCount: (delta: number) => void;
  refreshCartCount: () => Promise<void>;
  refreshWishlistCount: () => Promise<void>;
  refreshCompareCount: () => Promise<void>;
  refreshAllCounts: () => Promise<void>;
}

export const CountersContext = createContext<CountersContextType | undefined>(undefined);

interface CountersProviderProps {
  children: ReactNode;
  initialCounters?: {
    cartCount: number;
    wishlistCount: number;
    compareCount: number;
  };
}

export function CountersProvider({ children, initialCounters }: CountersProviderProps) {
  const [cartCount, setCartCount] = useState(initialCounters?.cartCount || 0);
  const [wishlistCount, setWishlistCount] = useState(initialCounters?.wishlistCount || 0);
  const [compareCount, setCompareCount] = useState(initialCounters?.compareCount || 0);
  const [isLoading, setIsLoading] = useState(!initialCounters);
  const inFlightRef = useRef(false);
  const lastRefreshAtRef = useRef(0);

  const setCartCountValue = useCallback((count: number) => {
    setCartCount(Math.max(0, count));
  }, []);

  const bumpCartCount = useCallback((delta: number) => {
    if (delta === 0) return;
    setCartCount((prev) => Math.max(0, prev + delta));
  }, []);

  const refreshCartCount = useCallback(async () => {
    try {
      const data = await api.cart.count();
      setCartCountValue(data.count || 0);
    } catch {
      setCartCountValue(0);
    }
  }, [setCartCountValue]);

  const refreshWishlistCount = useCallback(async () => {
    try {
      const data = await api.wishlist.count();
      const newCount = data.count || 0;
      setWishlistCount(newCount);
    } catch {
      setWishlistCount(0);
    }
  }, []);

  const refreshCompareCount = useCallback(async () => {
    try {
      const data = await api.compare.count();
      const newCount = data.count || 0;
      setCompareCount(newCount);
    } catch {
      setCompareCount(0);
    }
  }, []);

  const refreshAllCounts = useCallback(async () => {
    const now = Date.now();
    if (inFlightRef.current) return;
    // Защита от частых повторных вызовов (focus/visibility/interval).
    if (now - lastRefreshAtRef.current < 10_000) return;

    try {
      inFlightRef.current = true;
      lastRefreshAtRef.current = now;
      setIsLoading(true);
      await Promise.all([
        refreshCartCount(),
        refreshWishlistCount(),
        refreshCompareCount(),
      ]);
    } catch {
      // ignore
    } finally {
      inFlightRef.current = false;
      setIsLoading(false);
    }
  }, [refreshCartCount, refreshWishlistCount, refreshCompareCount]);

  // Загружаем счетчики при монтировании только если нет initialCounters
  useEffect(() => {
    if (initialCounters) {
      setIsLoading(false);
      return;
    }
    refreshAllCounts();
  }, [refreshAllCounts, initialCounters]);

  // Обновляем счетчики при возвращении на вкладку
  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        refreshAllCounts();
      }
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => {
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [refreshAllCounts]);

  // Обновляем счетчики периодически (каждые 60 секунд, только когда вкладка активна)
  useEffect(() => {
    const interval = setInterval(() => {
      if (document.visibilityState === 'visible') {
        refreshAllCounts();
      }
    }, 60000);
    return () => clearInterval(interval);
  }, [refreshAllCounts]);

  const value: CountersContextType = useMemo(
    () => ({
      cartCount,
      wishlistCount,
      compareCount,
      isLoading,
      setCartCount: setCartCountValue,
      bumpCartCount,
      refreshCartCount,
      refreshWishlistCount,
      refreshCompareCount,
      refreshAllCounts,
    }),
    [
      cartCount,
      wishlistCount,
      compareCount,
      isLoading,
      setCartCountValue,
      bumpCartCount,
      refreshCartCount,
      refreshWishlistCount,
      refreshCompareCount,
      refreshAllCounts,
    ],
  );

  return <CountersContext.Provider value={value}>{children}</CountersContext.Provider>;
}
