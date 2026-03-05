import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function VendorEmailsPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [vendor, setVendor] = useState(null);
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    setError('');
    Promise.all([
      api.get(`/vendors/${id}`),
      api.get(`/vendors/${id}/emails`),
    ])
      .then(([v, e]) => {
        setVendor(v.vendor || v);
        setEmails(e.emails || []);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

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
        <h1 className="page-header__title">{vendor?.name || 'Vendor'}</h1>
        <p className="page-header__subtitle">{vendor?.contact_email || ''}</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Emails</span>
        </div>
        {emails.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No emails found</div>
            <p className="empty-state__description">No emails have been received from this vendor yet.</p>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>From</th>
                <th>Date</th>
                <th>Status</th>
                <th>Invoice</th>
              </tr>
            </thead>
            <tbody>
              {emails.map((email) => (
                <tr key={email.id}>
                  <td>{email.subject}</td>
                  <td>{email.from_address || email.sender}</td>
                  <td>{email.received_at ? new Date(email.received_at).toLocaleDateString() : ''}</td>
                  <td>
                    <span className={`badge badge--${email.processing_status === 'completed' ? 'success' : email.processing_status === 'error' ? 'danger' : 'warning'}`}>
                      {email.processing_status}
                    </span>
                  </td>
                  <td>
                    {email.has_invoice ? (
                      <span className="badge badge--success">Yes</span>
                    ) : (
                      <span className="badge badge--neutral">No</span>
                    )}
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
