import { createContext, useState, useEffect, useCallback, ReactNode, useRef, useMemo } from 'react';
import { api, type User } from '../lib/api';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: { name: string; email: string; password: string; password_confirmation: string; phone?: string }) => Promise<void>;
  logout: () => Promise<void>;
  updateProfile: (data: { name?: string; phone?: string }) => Promise<void>;
  refreshUser: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
  initialUser?: User | null;
}

export function AuthProvider({ children, initialUser = null }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(initialUser);
  const [isLoading, setIsLoading] = useState(!initialUser);
  const isRefreshingRef = useRef(false);

  const refreshUser = useCallback(async (force = false) => {
    // Предотвращаем параллельные запросы
    if (isRefreshingRef.current && !force) {
      return;
    }

    isRefreshingRef.current = true;
    try {
      const response = await api.auth.me();
      setUser(response.user);
      return true;
    } catch (error: any) {
      if (error?.status === 401) {
        setUser(null);
      }
      return false;
    } finally {
      isRefreshingRef.current = false;
    }
  }, []);

  useEffect(() => {
    // Если есть initialUser, не загружаем повторно
    if (initialUser) {
      setIsLoading(false);
      return;
    }

    // Загружаем пользователя при монтировании только если нет initialUser
    let mounted = true;
    
    refreshUser().then((success) => {
      if (mounted) {
        setIsLoading(false);
      }
    }).catch(() => {
      if (mounted) {
        setIsLoading(false);
      }
    });

    return () => {
      mounted = false;
    };
  }, [refreshUser, initialUser]);

  const login = useCallback(async (email: string, password: string) => {
    const response = await api.auth.login(email, password);
    // Устанавливаем пользователя сразу после успешного логина
    setUser(response.user);
    setIsLoading(false);
  }, []);

  const register = useCallback(async (data: { name: string; email: string; password: string; password_confirmation: string; phone?: string }) => {
    const response = await api.auth.register(data);
    setUser(response.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.auth.logout();
    } catch (error) {
      // Игнорируем ошибки при выходе
    } finally {
      setUser(null);
    }
  }, []);

  const updateProfile = useCallback(async (data: { name?: string; phone?: string }) => {
    const response = await api.auth.updateProfile(data);
    setUser(response.user);
  }, []);

  // Мемоизируем value, чтобы избежать лишних ререндеров
  const value: AuthContextType = useMemo(() => ({
    user,
    isLoading,
    isAuthenticated: !!user,
    login,
    register,
    logout,
    updateProfile,
    refreshUser,
  }), [user, isLoading, login, register, logout, updateProfile, refreshUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
