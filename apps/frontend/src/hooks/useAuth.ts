import { useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';

// Значения по умолчанию для случая, когда контекст не инициализирован
const defaultAuthValue = {
  user: null,
  isLoading: true,
  isAuthenticated: false,
  login: async (_email: string, _password: string) => { 
    throw new Error('AuthProvider not initialized'); 
  },
  register: async (_data: any) => { 
    throw new Error('AuthProvider not initialized'); 
  },
  logout: async () => { 
    throw new Error('AuthProvider not initialized'); 
  },
  updateProfile: async (_data: any) => { 
    throw new Error('AuthProvider not initialized'); 
  },
  refreshUser: async () => { 
    throw new Error('AuthProvider not initialized'); 
  },
};

export function useAuth() {
  const context = useContext(AuthContext);
  
  // Всегда возвращаем валидный объект
  return context ?? defaultAuthValue;
}
