import { createContext, useContext, useMemo, ReactNode } from 'react'
import type { SSRContext } from '../types/ssr'

interface SSRContextType {
  data: SSRContext
}

const SSRContext = createContext<SSRContextType | undefined>(undefined)

interface SSRProviderProps {
  children: ReactNode
  data: SSRContext
}

/** Стабильная ссылка value, чтобы не вызывать лишние ре-рендеры и циклы в потребителях (useEffect с initialProducts). */
export function SSRProvider({ children, data }: SSRProviderProps) {
  const value = useMemo(() => ({ data }), [data])
  return (
    <SSRContext.Provider value={value}>
      {children}
    </SSRContext.Provider>
  )
}

export function useSSR() {
  const context = useContext(SSRContext)
  return context?.data
}
