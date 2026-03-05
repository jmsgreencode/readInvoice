import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function VendorRequestDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [request, setRequest] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');
  const [reviewNotes, setReviewNotes] = useState('');
  const [reviewDecision, setReviewDecision] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/vendor-requests/${id}`)
      .then((data) => setRequest(data.vendor_request || data.request || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleReview = async (decision) => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/vendor-requests/${id}/review`, { decision, notes: reviewNotes });
      setActionMessage(`Request ${decision}d.`);
      setRequest((prev) => ({ ...prev, status: decision === 'approve' ? 'approved' : 'rejected' }));
    } catch (err) {
      setActionMessage(err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handlePromote = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/vendor-requests/${id}/promote`);
      setActionMessage('Vendor promoted successfully.');
      setRequest((prev) => ({ ...prev, status: 'promoted' }));
    } catch (err) {
      setActionMessage(err.message);
    } finally {
      setActionLoading(false);
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

  if (!request) return null;

  const canReview = request.status === 'submitted' || request.status === 'under_review';
  const canPromote = request.status === 'approved';

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Vendor Request {request.request_number || `#${request.id}`}</h1>
        <p className="page-header__subtitle"><StatusBadge status={request.status} /></p>
      </div>

      {actionMessage && <div className="alert alert--success" style={{ marginBottom: 16 }}>{actionMessage}</div>}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Request Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Vendor Name</div>
              <div className="invoice-detail__value">{request.vendor_name}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Website</div>
              <div className="invoice-detail__value">{request.vendor_website || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Contact Name</div>
              <div className="invoice-detail__value">{request.vendor_contact_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Contact Email</div>
              <div className="invoice-detail__value">{request.vendor_contact_email || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Contact Phone</div>
              <div className="invoice-detail__value">{request.vendor_contact_phone || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Category</div>
              <div className="invoice-detail__value">{request.category || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Business Justification</div>
              <div className="invoice-detail__value">{request.business_justification || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Requestor</div>
              <div className="invoice-detail__value">{request.requestor_name || request.requestor || 'N/A'}</div>
            </div>
          </div>
        </div>
      </div>

      {canReview && (
        <div className="card" style={{ marginBottom: 16, maxWidth: 500 }}>
          <div className="card__header">
            <span className="card__title">Review</span>
          </div>
          <div className="card__body">
            <div className="form-group">
              <label className="form-label" htmlFor="reviewNotes">Notes</label>
              <textarea id="reviewNotes" className="form-input" rows={3} value={reviewNotes} onChange={(e) => setReviewNotes(e.target.value)} />
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button className="btn btn--primary" onClick={() => handleReview('approve')} disabled={actionLoading}>Approve</button>
              <button className="btn btn--danger" onClick={() => handleReview('reject')} disabled={actionLoading}>Reject</button>
            </div>
          </div>
        </div>
      )}

      {canPromote && (
        <button className="btn btn--primary" onClick={handlePromote} disabled={actionLoading}>
          {actionLoading ? 'Promoting...' : 'Promote to Vendor'}
        </button>
      )}
    </>
  );
}
