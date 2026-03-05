import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function RequisitionDetailPage() {
  const { id } = useParams();
  const [requisition, setRequisition] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [showRejectForm, setShowRejectForm] = useState(false);

  useEffect(() => {
    setLoading(true);
    api.get(`/requisitions/${id}`)
      .then((data) => setRequisition(data.requisition || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleApprove = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/requisitions/${id}/approve`);
      setActionMessage('Requisition approved.');
      setRequisition((prev) => ({ ...prev, status: 'approved' }));
    } catch (err) {
      setActionMessage(err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/requisitions/${id}/reject`, { reason: rejectReason });
      setActionMessage('Requisition rejected.');
      setRequisition((prev) => ({ ...prev, status: 'rejected' }));
      setShowRejectForm(false);
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

  if (!requisition) return null;

  const lineItems = requisition.line_items || [];

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Requisition {requisition.requisition_number || `#${requisition.id}`}</h1>
        <p className="page-header__subtitle">{requisition.department_name || ''}</p>
      </div>

      {actionMessage && <div className="alert alert--success" style={{ marginBottom: 16 }}>{actionMessage}</div>}

      {requisition.status === 'submitted' && (
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <button className="btn btn--primary" onClick={handleApprove} disabled={actionLoading}>Approve</button>
          <button className="btn btn--danger" onClick={() => setShowRejectForm(!showRejectForm)} disabled={actionLoading}>Reject</button>
        </div>
      )}

      {showRejectForm && (
        <div className="card" style={{ marginBottom: 16, maxWidth: 500 }}>
          <div className="card__body">
            <div className="form-group">
              <label className="form-label" htmlFor="rejectReason">Rejection Reason</label>
              <textarea id="rejectReason" className="form-input" rows={3} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} required />
            </div>
            <button className="btn btn--danger" onClick={handleReject} disabled={actionLoading || !rejectReason}>
              Confirm Reject
            </button>
          </div>
        </div>
      )}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Status</div>
              <div className="invoice-detail__value"><StatusBadge status={requisition.status} /></div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Priority</div>
              <div className="invoice-detail__value">{requisition.priority || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Requestor</div>
              <div className="invoice-detail__value">{requisition.requestor_name || requisition.requestor || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Department</div>
              <div className="invoice-detail__value">{requisition.department_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Vendor</div>
              <div className="invoice-detail__value">{requisition.vendor_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Justification</div>
              <div className="invoice-detail__value">{requisition.justification || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Total Amount</div>
              <div className="invoice-detail__amount">{requisition.total_amount != null ? `$${Number(requisition.total_amount).toFixed(2)}` : 'N/A'}</div>
            </div>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card__header">
          <span className="card__title">Line Items</span>
        </div>
        {lineItems.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No line items</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              {lineItems.map((item, i) => (
                <tr key={item.id || i}>
                  <td>{item.description}</td>
                  <td>{item.quantity}</td>
                  <td>${Number(item.unit_price).toFixed(2)}</td>
                  <td>${(Number(item.quantity) * Number(item.unit_price)).toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
