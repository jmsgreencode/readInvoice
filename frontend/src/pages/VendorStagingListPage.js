import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function VendorStagingListPage() {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  useEffect(() => {
    setLoading(true);
    const url = statusFilter ? `/staging?validation_status=${statusFilter}` : '/staging';
    api.get(url)
      .then((data) => setRecords(data.records || data.staging || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [statusFilter]);

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
        <h1 className="page-header__title">Vendor Staging</h1>
        <p className="page-header__subtitle">Review and promote staged vendor records</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Staged Vendors</span>
          <div style={{ display: 'flex', gap: 8, marginLeft: 'auto' }}>
            <select className="form-input" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="valid">Valid</option>
              <option value="invalid">Invalid</option>
            </select>
            <Link to="/staging/import" className="btn btn--primary">Upload CSV</Link>
          </div>
        </div>
        {records.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No staged vendors found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Batch ID</th>
                <th>Name</th>
                <th>Domain</th>
                <th>Validation Status</th>
                <th>Promoted Vendor ID</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {records.map((rec) => (
                <tr key={rec.id}>
                  <td>{rec.batch_id}</td>
                  <td>{rec.name}</td>
                  <td>{rec.domain || 'N/A'}</td>
                  <td><StatusBadge status={rec.validation_status} /></td>
                  <td>{rec.promoted_vendor_id || 'N/A'}</td>
                  <td><Link to={`/staging/${rec.id}`} className="btn btn--primary btn--sm">Review</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
