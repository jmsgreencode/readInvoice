import { useEffect, useState } from 'react';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function GrnListPage() {
  const [grns, setGrns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/grns')
      .then((data) => setGrns(data.grns || data || []))
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
        <h1 className="page-header__title">Goods Received Notes</h1>
        <p className="page-header__subtitle">Track goods received against purchase orders</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All GRNs</span>
        </div>
        {grns.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No GRNs found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>GRN Number</th>
                <th>PO Number</th>
                <th>Received By</th>
                <th>Status</th>
                <th>Received Date</th>
              </tr>
            </thead>
            <tbody>
              {grns.map((grn) => (
                <tr key={grn.id}>
                  <td>{grn.grn_number}</td>
                  <td>{grn.po_number}</td>
                  <td>{grn.received_by || 'N/A'}</td>
                  <td><StatusBadge status={grn.status} /></td>
                  <td>{grn.received_date ? new Date(grn.received_date).toLocaleDateString() : 'N/A'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
