import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function RequisitionListPage() {
  const navigate = useNavigate();
  const [requisitions, setRequisitions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  useEffect(() => {
    setLoading(true);
    const url = statusFilter ? `/requisitions?status=${statusFilter}` : '/requisitions';
    api.get(url)
      .then((data) => setRequisitions(data.requisitions || data || []))
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
        <h1 className="page-header__title">Requisitions</h1>
        <p className="page-header__subtitle">Purchase requisition tracking</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Requisitions</span>
          <div className="form-group" style={{ marginBottom: 0, marginLeft: 'auto' }}>
            <select className="form-input" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        {requisitions.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No requisitions found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Department</th>
                <th>Requestor</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Total Amount</th>
              </tr>
            </thead>
            <tbody>
              {requisitions.map((req) => (
                <tr
                  key={req.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => navigate(`/requisitions/${req.id}`)}
                >
                  <td>{req.requisition_number || req.number}</td>
                  <td>{req.department_name || req.department}</td>
                  <td>{req.requestor_name || req.requestor}</td>
                  <td><StatusBadge status={req.status} /></td>
                  <td>{req.priority || 'N/A'}</td>
                  <td>{req.total_amount != null ? `$${Number(req.total_amount).toFixed(2)}` : 'N/A'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
