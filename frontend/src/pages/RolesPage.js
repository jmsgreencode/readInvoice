import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function RolesPage() {
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [name, setName] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [formError, setFormError] = useState('');
  const [formSuccess, setFormSuccess] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchRoles = () => {
    setLoading(true);
    api.get('/roles')
      .then((data) => setRoles(data.roles || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchRoles();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    setFormSuccess('');
    setSubmitting(true);
    try {
      await api.post('/roles', { name, display_name: displayName });
      setFormSuccess('Role created successfully.');
      setName('');
      setDisplayName('');
      fetchRoles();
    } catch (err) {
      setFormError(err.message);
    } finally {
      setSubmitting(false);
    }
  };

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
        <h1 className="page-header__title">Roles</h1>
        <p className="page-header__subtitle">Manage user roles and permissions</p>
      </div>

      <div className="card" style={{ maxWidth: 500, marginBottom: 24 }}>
        <div className="card__header">
          <span className="card__title">Create Role</span>
        </div>
        <div className="card__body">
          {formError && <div className="alert alert--error">{formError}</div>}
          {formSuccess && <div className="alert alert--success">{formSuccess}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="roleName">Name</label>
              <input id="roleName" className="form-input" type="text" value={name} onChange={(e) => setName(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="roleDisplayName">Display Name</label>
              <input id="roleDisplayName" className="form-input" type="text" value={displayName} onChange={(e) => setDisplayName(e.target.value)} required />
            </div>
            <button className="btn btn--primary" type="submit" disabled={submitting}>
              {submitting ? 'Creating...' : 'Create Role'}
            </button>
          </form>
        </div>
      </div>

      <div className="card">
        <div className="card__header">
          <span className="card__title">All Roles</span>
        </div>
        {roles.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No roles found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Display Name</th>
                <th>System</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {roles.map((role) => (
                <tr key={role.id}>
                  <td>{role.name}</td>
                  <td>{role.display_name}</td>
                  <td>
                    <span className={`badge badge--${role.is_system ? 'warning' : 'neutral'}`}>
                      {role.is_system ? 'Yes' : 'No'}
                    </span>
                  </td>
                  <td><Link to={`/roles/${role.id}`} className="btn btn--primary btn--sm">Edit</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
