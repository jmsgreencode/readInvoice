import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';

export default function RoleEditPage() {
  const { id } = useParams();
  const [role, setRole] = useState(null);
  const [allPermissions, setAllPermissions] = useState([]);
  const [selectedPermissions, setSelectedPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');

  useEffect(() => {
    setLoading(true);
    Promise.all([api.get(`/roles/${id}`), api.get('/permissions')])
      .then(([r, p]) => {
        const roleData = r.role || r;
        setRole(roleData);
        setAllPermissions(p.permissions || p || []);
        setSelectedPermissions((roleData.permissions || []).map((perm) => perm.id || perm));
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const togglePermission = (permId) => {
    setSelectedPermissions((prev) =>
      prev.includes(permId) ? prev.filter((p) => p !== permId) : [...prev, permId]
    );
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setSaving(true);
    try {
      await api.post(`/roles/${id}/permissions`, { permission_ids: selectedPermissions });
      setSuccess('Permissions updated successfully.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
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

  if (error && !role) {
    return <div className="alert alert--error">{error}</div>;
  }

  if (!role) return null;

  const grouped = {};
  allPermissions.forEach((perm) => {
    const module = perm.module || 'General';
    if (!grouped[module]) grouped[module] = [];
    grouped[module].push(perm);
  });

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Edit Role: {role.display_name || role.name}</h1>
        <p className="page-header__subtitle">Manage permissions for this role</p>
      </div>

      {error && <div className="alert alert--error" style={{ marginBottom: 16 }}>{error}</div>}
      {success && <div className="alert alert--success" style={{ marginBottom: 16 }}>{success}</div>}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Role Info</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Name</div>
              <div className="invoice-detail__value">{role.name}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Display Name</div>
              <div className="invoice-detail__value">{role.display_name}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">System Role</div>
              <div className="invoice-detail__value">{role.is_system ? 'Yes' : 'No'}</div>
            </div>
          </div>
        </div>
      </div>

      <form onSubmit={handleSave}>
        {Object.entries(grouped).map(([module, perms]) => (
          <div key={module} className="card" style={{ marginBottom: 16 }}>
            <div className="card__header">
              <span className="card__title">{module}</span>
            </div>
            <div className="card__body">
              {perms.map((perm) => (
                <div key={perm.id} style={{ marginBottom: 8 }}>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <input
                      type="checkbox"
                      checked={selectedPermissions.includes(perm.id)}
                      onChange={() => togglePermission(perm.id)}
                    />
                    <span>{perm.display_name || perm.name}</span>
                    {perm.description && <span style={{ color: '#888', fontSize: '0.85em' }}>- {perm.description}</span>}
                  </label>
                </div>
              ))}
            </div>
          </div>
        ))}

        <button className="btn btn--primary" type="submit" disabled={saving}>
          {saving ? 'Saving...' : 'Save Permissions'}
        </button>
      </form>
    </>
  );
}
