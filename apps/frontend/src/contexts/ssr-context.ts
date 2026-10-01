import { createContext, useContext } from 'react';
import type { SSRContext as SSRData } from '../types/ssr';

export interface SSRContextType {
  data: SSRData;
}

export const SSRContext = createContext<SSRContextType | undefined>(undefined);

export function useSSR() {
  const context = useContext(SSRContext);
  return context?.data;
}
