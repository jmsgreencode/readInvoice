import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function PurchaseOrderDetailPage() {
  const { id } = useParams();
  const [po, setPo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/purchase-orders/${id}`)
      .then((data) => setPo(data.purchase_order || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleIssuePO = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.put(`/purchase-orders/${id}`, { status: 'issued' });
      setActionMessage('Purchase order issued successfully.');
      setPo((prev) => ({ ...prev, status: 'issued' }));
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

  if (!po) return null;

  const lineItems = po.line_items || [];

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Purchase Order {po.po_number}</h1>
        <p className="page-header__subtitle">{po.vendor_name || ''}</p>
      </div>

      {actionMessage && <div className="alert alert--success" style={{ marginBottom: 16 }}>{actionMessage}</div>}

      {po.status === 'draft' && (
        <div style={{ marginBottom: 16 }}>
          <button className="btn btn--primary" onClick={handleIssuePO} disabled={actionLoading}>
            Issue PO
          </button>
        </div>
      )}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">PO Number</div>
              <div className="invoice-detail__value">{po.po_number}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Status</div>
              <div className="invoice-detail__value"><StatusBadge status={po.status} /></div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Vendor</div>
              <div className="invoice-detail__value">{po.vendor_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Total Amount</div>
              <div className="invoice-detail__amount">{po.total_amount != null ? `$${Number(po.total_amount).toFixed(2)}` : 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Created</div>
              <div className="invoice-detail__value">{po.created_at ? new Date(po.created_at).toLocaleString() : 'N/A'}</div>
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
