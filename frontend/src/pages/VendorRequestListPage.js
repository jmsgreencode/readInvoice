import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function VendorRequestListPage() {
  const navigate = useNavigate();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/vendor-requests')
      .then((data) => setRequests(data.vendor_requests || data.requests || data || []))
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
        <h1 className="page-header__title">Vendor Requests</h1>
        <p className="page-header__subtitle">New vendor onboarding requests</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Requests</span>
        </div>
        {requests.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No vendor requests found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Request Number</th>
                <th>Vendor Name</th>
                <th>Requestor</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {requests.map((req) => (
                <tr
                  key={req.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => navigate(`/vendor-requests/${req.id}`)}
                >
                  <td>{req.request_number}</td>
                  <td>{req.vendor_name}</td>
                  <td>{req.requestor_name || req.requestor || 'N/A'}</td>
                  <td><StatusBadge status={req.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
