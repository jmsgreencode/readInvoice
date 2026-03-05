import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function UserEditPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [displayName, setDisplayName] = useState('');
  const [email, setEmail] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [roleAction, setRoleAction] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/users/${id}`)
      .then((data) => {
        const u = data.user || data;
        setUser(u);
        setDisplayName(u.display_name || '');
        setEmail(u.email || '');
        setDepartmentId(u.department_id || '');
        setIsActive(u.is_active !== false);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleSave = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setSaving(true);
    try {
      await api.put(`/users/${id}`, {
        display_name: displayName,
        email,
        department_id: departmentId ? Number(departmentId) : null,
        is_active: isActive,
      });
      setSuccess('User updated successfully.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleAssignRole = async (roleId) => {
    setError('');
    setSuccess('');
    try {
      await api.post(`/users/${id}/roles`, { role_id: roleId });
      setSuccess('Role assigned.');
      const data = await api.get(`/users/${id}`);
      setUser(data.user || data);
    } catch (err) {
      setError(err.message);
    }
  };

  const handleRemoveRole = async (roleId) => {
    setError('');
    setSuccess('');
    try {
      await api.delete(`/users/${id}/roles/${roleId}`);
      setSuccess('Role removed.');
      const data = await api.get(`/users/${id}`);
      setUser(data.user || data);
    } catch (err) {
      setError(err.message);
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

  if (error && !user) {
    return <div className="alert alert--error">{error}</div>;
  }

  if (!user) return null;

  const userRoles = user.roles || [];

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Edit User: {user.username}</h1>
        <p className="page-header__subtitle">{user.email || ''}</p>
      </div>

      {error && <div className="alert alert--error" style={{ marginBottom: 16 }}>{error}</div>}
      {success && <div className="alert alert--success" style={{ marginBottom: 16 }}>{success}</div>}

      <div className="card" style={{ marginBottom: 16, maxWidth: 500 }}>
        <div className="card__header">
          <span className="card__title">User Details</span>
        </div>
        <div className="card__body">
          <form onSubmit={handleSave}>
            <div className="form-group">
              <label className="form-label" htmlFor="userDisplayName">Display Name</label>
              <input id="userDisplayName" className="form-input" type="text" value={displayName} onChange={(e) => setDisplayName(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="userEmail">Email</label>
              <input id="userEmail" className="form-input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="userDept">Department ID</label>
              <input id="userDept" className="form-input" type="number" value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} />
            </div>
            <div className="form-group">
              <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                <span className="form-label" style={{ marginBottom: 0 }}>Active</span>
              </label>
            </div>
            <button className="btn btn--primary" type="submit" disabled={saving}>
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
          </form>
        </div>
      </div>

      <div className="card">
        <div className="card__header">
          <span className="card__title">Roles</span>
        </div>
        <div className="card__body">
          {userRoles.length === 0 ? (
            <p>No roles assigned.</p>
          ) : (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
              {userRoles.map((role, i) => {
                const roleId = role.id || role;
                const roleName = typeof role === 'string' ? role : role.display_name || role.name;
                return (
                  <span key={i} className="badge badge--neutral" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                    {roleName}
                    <button
                      type="button"
                      style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#e74c3c', fontWeight: 'bold', padding: 0 }}
                      onClick={() => handleRemoveRole(roleId)}
                    >
                      x
                    </button>
                  </span>
                );
              })}
            </div>
          )}
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <input
              className="form-input"
              type="number"
              placeholder="Role ID"
              value={roleAction}
              onChange={(e) => setRoleAction(e.target.value)}
              style={{ width: 120 }}
            />
            <button className="btn btn--primary btn--sm" onClick={() => { if (roleAction) handleAssignRole(Number(roleAction)); }}>Assign Role</button>
          </div>
        </div>
      </div>
    </>
  );
}
