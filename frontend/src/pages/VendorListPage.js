import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function VendorListPage() {
  const navigate = useNavigate();
  const [vendors, setVendors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/vendors')
      .then((data) => setVendors(data.vendors || data || []))
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
        <h1 className="page-header__title">Vendors</h1>
        <p className="page-header__subtitle">Manage your vendor directory</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Vendors</span>
        </div>
        {vendors.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No vendors found</div>
            <p className="empty-state__description">Add a vendor to get started.</p>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Domain</th>
                <th>Verification Status</th>
                <th>Risk Rating</th>
                <th>Expiry Date</th>
                <th>Blocked</th>
              </tr>
            </thead>
            <tbody>
              {vendors.map((vendor) => (
                <tr
                  key={vendor.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => navigate(`/vendors/${vendor.id}`)}
                >
                  <td>{vendor.name}</td>
                  <td>{vendor.domain || 'N/A'}</td>
                  <td><StatusBadge status={vendor.verification_status} /></td>
                  <td>{vendor.risk_rating || 'N/A'}</td>
                  <td>{vendor.expiry_date ? new Date(vendor.expiry_date).toLocaleDateString() : 'N/A'}</td>
                  <td>
                    <span className={`badge badge--${vendor.is_blocked ? 'danger' : 'success'}`}>
                      {vendor.is_blocked ? 'Yes' : 'No'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
