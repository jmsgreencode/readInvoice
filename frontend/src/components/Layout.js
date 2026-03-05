import { Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Sidebar from './Sidebar';

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <>
      <header className="app-header">
        <div className="app-header__left">
          <a href="/dashboard" className="app-header__brand">proCom</a>
        </div>
        <div className="app-header__actions">
          <span className="app-header__user">{user?.username || 'User'}</span>
          <button className="btn btn--secondary btn--sm" onClick={handleLogout}>
            Logout
          </button>
        </div>
      </header>
      <div className="app-layout">
        <Sidebar />
        <main className="main-content">
          <Outlet />
        </main>
      </div>
    </>
  );
}
