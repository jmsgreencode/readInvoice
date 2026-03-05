import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from '../context/AuthContext';
import ProtectedRoute from './ProtectedRoute';

jest.mock('../api', () => ({ api: { post: jest.fn() } }));

beforeEach(() => localStorage.clear());

function renderWithRouter(initialPath) {
  return render(
    <AuthProvider>
      <MemoryRouter initialEntries={[initialPath]}>
        <Routes>
          <Route path="/login" element={<div>Login Page</div>} />
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <div>Dashboard</div>
              </ProtectedRoute>
            }
          />
        </Routes>
      </MemoryRouter>
    </AuthProvider>
  );
}

describe('ProtectedRoute', () => {
  test('redirects to /login when not authenticated', () => {
    renderWithRouter('/dashboard');
    expect(screen.getByText('Login Page')).toBeTruthy();
  });

  test('renders children when authenticated', () => {
    localStorage.setItem('user', JSON.stringify({ username: 'admin' }));
    renderWithRouter('/dashboard');
    expect(screen.getByText('Dashboard')).toBeTruthy();
  });
});
