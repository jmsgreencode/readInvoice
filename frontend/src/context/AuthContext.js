import { createContext, useContext, useState, useCallback } from 'react';
import { api } from '../api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      const stored = localStorage.getItem('user');
      return stored ? JSON.parse(stored) : null;
    } catch {
      localStorage.removeItem('user');
      localStorage.removeItem('token');
      return null;
    }
  });

  const login = useCallback(async (username, password) => {
    const data = await api.post('/auth/login', { username, password });
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    setUser(data.user);
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setUser(null);
  }, []);

  const hasPermission = useCallback((permissionName) => {
    if (!user || !user.permissions) return false;
    return user.permissions.includes(permissionName);
  }, [user]);

  const hasRole = useCallback((roleName) => {
    if (!user || !user.roles) return false;
    return user.roles.includes(roleName);
  }, [user]);

  const isSuperAdmin = useCallback(() => {
    return hasRole('super_admin');
  }, [hasRole]);

  return (
    <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user, hasPermission, hasRole, isSuperAdmin }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
