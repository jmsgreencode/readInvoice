import { useEffect, useState } from 'react';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';
import { Link } from 'react-router-dom';

export default function MatchDashboardPage() {
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  useEffect(() => {
    setLoading(true);
    const url = statusFilter ? `/matching?status=${statusFilter}` : '/matching';
    api.get(url)
      .then((data) => setMatches(data.match_results || data || []))
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
        <h1 className="page-header__title">3-Way Matching</h1>
        <p className="page-header__subtitle">PO, GRN, and Invoice matching dashboard</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Match Results</span>
          <div className="form-group" style={{ marginBottom: 0, marginLeft: 'auto' }}>
            <select className="form-input" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">All Statuses</option>
              <option value="matched">Matched</option>
              <option value="mismatched">Mismatched</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>
        {matches.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No match results found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>PO Number</th>
                <th>Invoice Number</th>
                <th>Status</th>
                <th>PO Amount</th>
                <th>Invoice Amount</th>
                <th>Matched At</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {matches.map((m) => (
                <tr key={m.id}>
                  <td>{m.po_number}</td>
                  <td>{m.invoice_number}</td>
                  <td><StatusBadge status={m.status} /></td>
                  <td>{m.po_amount != null ? `$${Number(m.po_amount).toFixed(2)}` : 'N/A'}</td>
                  <td>{m.invoice_amount != null ? `$${Number(m.invoice_amount).toFixed(2)}` : 'N/A'}</td>
                  <td>{m.matched_at ? new Date(m.matched_at).toLocaleString() : 'N/A'}</td>
                  <td><Link to={`/matching/${m.id}`} className="btn btn--primary btn--sm">View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
