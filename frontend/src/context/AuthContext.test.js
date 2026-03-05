import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AuthProvider, useAuth } from './AuthContext';

// Mock the api module
jest.mock('../api', () => ({
  api: {
    post: jest.fn(),
  },
}));

import { api } from '../api';

function TestConsumer() {
  const { user, isAuthenticated, login, logout } = useAuth();
  return (
    <div>
      <span data-testid="auth">{isAuthenticated ? 'yes' : 'no'}</span>
      <span data-testid="user">{user?.username || 'none'}</span>
      <button onClick={() => login('admin', 'pass')}>Login</button>
      <button onClick={() => logout()}>Logout</button>
    </div>
  );
}

beforeEach(() => {
  localStorage.clear();
  jest.clearAllMocks();
});

describe('AuthContext', () => {
  test('starts unauthenticated when no stored user', () => {
    render(
      <AuthProvider><TestConsumer /></AuthProvider>
    );
    expect(screen.getByTestId('auth').textContent).toBe('no');
    expect(screen.getByTestId('user').textContent).toBe('none');
  });

  test('restores user from localStorage on mount', () => {
    localStorage.setItem('user', JSON.stringify({ username: 'admin', role: 'admin' }));

    render(
      <AuthProvider><TestConsumer /></AuthProvider>
    );
    expect(screen.getByTestId('auth').textContent).toBe('yes');
    expect(screen.getByTestId('user').textContent).toBe('admin');
  });

  test('login stores token and user, sets authenticated', async () => {
    api.post.mockResolvedValue({
      token: 'jwt-token',
      user: { id: 1, username: 'admin', role: 'admin' },
    });

    render(
      <AuthProvider><TestConsumer /></AuthProvider>
    );

    await act(async () => {
      await userEvent.click(screen.getByText('Login'));
    });

    expect(localStorage.getItem('token')).toBe('jwt-token');
    expect(JSON.parse(localStorage.getItem('user'))).toEqual({ id: 1, username: 'admin', role: 'admin' });
    expect(screen.getByTestId('auth').textContent).toBe('yes');
    expect(screen.getByTestId('user').textContent).toBe('admin');
  });

  test('logout clears token and user', async () => {
    localStorage.setItem('token', 'jwt-token');
    localStorage.setItem('user', JSON.stringify({ username: 'admin' }));

    render(
      <AuthProvider><TestConsumer /></AuthProvider>
    );

    await act(async () => {
      await userEvent.click(screen.getByText('Logout'));
    });

    expect(localStorage.getItem('token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
    expect(screen.getByTestId('auth').textContent).toBe('no');
  });
});
