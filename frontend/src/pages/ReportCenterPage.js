import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function ReportCenterPage() {
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/reports/available')
      .then((data) => setReports(data.reports || data || []))
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
        <h1 className="page-header__title">Report Center</h1>
        <p className="page-header__subtitle">Generate and view reports</p>
      </div>
      {reports.length === 0 ? (
        <div className="card">
          <div className="empty-state">
            <div className="empty-state__title">No reports available</div>
          </div>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: 16 }}>
          {reports.map((report) => (
            <Link key={report.type || report.id} to={`/reports/${report.type || report.id}`} style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="card" style={{ height: '100%' }}>
                <div className="card__header">
                  <span className="card__title">{report.name}</span>
                </div>
                <div className="card__body">
                  <p>{report.description || 'No description available.'}</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
