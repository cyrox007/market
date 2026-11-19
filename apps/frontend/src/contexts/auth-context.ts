import { createContext } from 'react';
import type { User, RegistrationData, ProfileUpdateData } from '../lib/api';

export interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: RegistrationData) => Promise<void>;
  logout: () => Promise<void>;
  updateProfile: (data: ProfileUpdateData) => Promise<void>;
  refreshUser: (force?: boolean) => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
