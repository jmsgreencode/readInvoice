import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function UserManagementPage() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/users')
      .then((data) => setUsers(data.users || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="loading-indicator active">
        <div className="spinner" />
        Loading...
      </div>
    );
  }

  if (error) {
    return <div className="alert alert--error">{error}</div>;
  }

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">User Management</h1>
        <p className="page-header__subtitle">Manage system users</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Users</span>
        </div>
        {users.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No users found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Display Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Active</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.id}>
                  <td>{user.username}</td>
                  <td>{user.display_name || 'N/A'}</td>
                  <td>{user.email || 'N/A'}</td>
                  <td>
                    {(user.roles || []).map((role, i) => (
                      <span key={i} className="badge badge--neutral" style={{ marginRight: 4 }}>
                        {typeof role === 'string' ? role : role.display_name || role.name}
                      </span>
                    ))}
                  </td>
                  <td>
                    <span className={`badge badge--${user.is_active ? 'success' : 'danger'}`}>
                      {user.is_active ? 'Yes' : 'No'}
                    </span>
                  </td>
                  <td><Link to={`/users/${user.id}`} className="btn btn--primary btn--sm">Edit</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
