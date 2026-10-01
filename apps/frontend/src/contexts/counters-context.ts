import { createContext } from 'react';

export interface CountersContextType {
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
