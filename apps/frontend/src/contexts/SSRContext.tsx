import { useMemo, type ReactNode } from 'react';
import type { SSRContext as SSRData } from '../types/ssr';
import { SSRContext } from './ssr-context';

interface SSRProviderProps {
  children: ReactNode;
  data: SSRData;
}

/** Стабильная ссылка value, чтобы не вызывать лишние ре-рендеры и циклы в потребителях (useEffect с initialProducts). */
export function SSRProvider({ children, data }: SSRProviderProps) {
  const value = useMemo(() => ({ data }), [data]);
  return <SSRContext.Provider value={value}>{children}</SSRContext.Provider>;
}
