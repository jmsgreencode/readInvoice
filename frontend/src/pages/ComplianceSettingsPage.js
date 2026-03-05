import { useEffect, useState } from 'react';
import { api } from '../api';

export default function ComplianceSettingsPage() {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/compliance/settings')
      .then((data) => setSettings(data.settings || data || {}))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  const handleChange = (key, value) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setSaving(true);
    try {
      await api.put('/compliance/settings', settings);
      setSuccess('Settings saved successfully.');
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

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Compliance Settings</h1>
        <p className="page-header__subtitle">Configure compliance rules and thresholds</p>
      </div>
      <div className="card" style={{ maxWidth: 600 }}>
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          {success && <div className="alert alert--success">{success}</div>}
          <form onSubmit={handleSubmit}>
            {Object.entries(settings).map(([key, value]) => (
              <div className="form-group" key={key}>
                <label className="form-label" htmlFor={`setting-${key}`}>{key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</label>
                <input
                  id={`setting-${key}`}
                  className="form-input"
                  type={typeof value === 'number' ? 'number' : typeof value === 'boolean' ? 'checkbox' : 'text'}
                  value={typeof value === 'boolean' ? undefined : value}
                  checked={typeof value === 'boolean' ? value : undefined}
                  onChange={(e) => {
                    if (typeof value === 'boolean') {
                      handleChange(key, e.target.checked);
                    } else if (typeof value === 'number') {
                      handleChange(key, Number(e.target.value));
                    } else {
                      handleChange(key, e.target.value);
                    }
                  }}
                />
              </div>
            ))}
            <button className="btn btn--primary" type="submit" disabled={saving}>
              {saving ? 'Saving...' : 'Save Settings'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
