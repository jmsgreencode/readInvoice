import { useEffect, useState } from 'react';
import { api } from '../api';

export default function ComplianceDashboardPage() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(null);

  useEffect(() => {
    fetchAlerts();
  }, []);

  const fetchAlerts = () => {
    setLoading(true);
    api.get('/compliance/alerts?resolved=false')
      .then((data) => setAlerts(data.alerts || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  const handleResolve = async (alertId) => {
    setActionLoading(alertId);
    try {
      await api.post(`/compliance/alerts/${alertId}/resolve`);
      setAlerts((prev) => prev.filter((a) => a.id !== alertId));
    } catch (err) {
      setError(err.message);
    } finally {
      setActionLoading(null);
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

  const severityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
  const sortedAlerts = [...alerts].sort(
    (a, b) => (severityOrder[a.severity] ?? 99) - (severityOrder[b.severity] ?? 99)
  );

  const grouped = {};
  sortedAlerts.forEach((alert) => {
    const sev = alert.severity || 'unknown';
    if (!grouped[sev]) grouped[sev] = [];
    grouped[sev].push(alert);
  });

  const severityColor = {
    critical: '#e74c3c',
    high: '#e67e22',
    medium: '#f39c12',
    low: '#3498db',
  };

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Compliance Dashboard</h1>
        <p className="page-header__subtitle">Active compliance alerts</p>
      </div>

      {alerts.length === 0 ? (
        <div className="card">
          <div className="empty-state">
            <div className="empty-state__title">No active alerts</div>
            <p className="empty-state__description">All compliance checks are passing.</p>
          </div>
        </div>
      ) : (
        Object.entries(grouped).map(([severity, items]) => (
          <div key={severity} className="card" style={{ marginBottom: 16 }}>
            <div className="card__header">
              <span className="card__title" style={{ color: severityColor[severity] || '#333' }}>
                {severity.charAt(0).toUpperCase() + severity.slice(1)} ({items.length})
              </span>
            </div>
            <div className="card__body">
              {items.map((alert) => (
                <div key={alert.id} style={{ borderBottom: '1px solid #eee', padding: '12px 0' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <div>
                      <strong>{alert.title || alert.type}</strong>
                      {alert.entity && <span style={{ marginLeft: 8, color: '#666' }}>({alert.entity})</span>}
                      <p style={{ margin: '4px 0 0', color: '#555' }}>{alert.message}</p>
                    </div>
                    <button
                      className="btn btn--primary btn--sm"
                      onClick={() => handleResolve(alert.id)}
                      disabled={actionLoading === alert.id}
                    >
                      {actionLoading === alert.id ? 'Resolving...' : 'Resolve'}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))
      )}
    </>
  );
}
