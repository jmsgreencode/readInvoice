import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../context/AuthContext';
import LoginPage from './LoginPage';

jest.mock('../api', () => ({
  api: {
    post: jest.fn(),
  },
}));

import { api } from '../api';

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

beforeEach(() => {
  localStorage.clear();
  jest.clearAllMocks();
});

function renderLogin() {
  return render(
    <AuthProvider>
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    </AuthProvider>
  );
}

describe('LoginPage', () => {
  test('renders login form', () => {
    renderLogin();
    expect(screen.getByLabelText('Username')).toBeTruthy();
    expect(screen.getByLabelText('Password')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Sign In' })).toBeTruthy();
  });

  test('successful login navigates to /dashboard', async () => {
    api.post.mockResolvedValue({
      token: 'jwt',
      user: { id: 1, username: 'admin', role: 'admin' },
    });

    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('Username'), 'admin');
    await user.type(screen.getByLabelText('Password'), 'admin123');
    await user.click(screen.getByRole('button', { name: 'Sign In' }));

    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      username: 'admin',
      password: 'admin123',
    });
    expect(mockNavigate).toHaveBeenCalledWith('/dashboard');
  });

  test('shows error on failed login', async () => {
    api.post.mockRejectedValue(new Error('Invalid credentials'));

    renderLogin();
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('Username'), 'admin');
    await user.type(screen.getByLabelText('Password'), 'wrong');
    await user.click(screen.getByRole('button', { name: 'Sign In' }));

    expect(await screen.findByText('Invalid credentials')).toBeTruthy();
  });
});
