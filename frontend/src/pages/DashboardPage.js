import { useAuth } from '../context/AuthContext';

export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Dashboard</h1>
        <p className="page-header__subtitle">Welcome back, {user?.username || 'User'}</p>
      </div>
      <div className="card">
        <div className="card__body">
          <p>Select a vendor from the sidebar to view their emails and invoices, or use the navigation to add vendors and upload invoices.</p>
        </div>
      </div>
    </>
  );
}
