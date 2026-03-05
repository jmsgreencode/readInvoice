import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function CreateGrnPage() {
  const navigate = useNavigate();
  const [purchaseOrders, setPurchaseOrders] = useState([]);
  const [selectedPoId, setSelectedPoId] = useState('');
  const [selectedPo, setSelectedPo] = useState(null);
  const [receivedDate, setReceivedDate] = useState('');
  const [lineItems, setLineItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.get('/purchase-orders?status=issued')
      .then((data) => setPurchaseOrders(data.purchase_orders || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  const handlePoSelect = async (poId) => {
    setSelectedPoId(poId);
    if (!poId) {
      setSelectedPo(null);
      setLineItems([]);
      return;
    }
    try {
      const data = await api.get(`/purchase-orders/${poId}`);
      const po = data.purchase_order || data;
      setSelectedPo(po);
      setLineItems(
        (po.line_items || []).map((li) => ({
          po_line_item_id: li.id,
          description: li.description,
          ordered_qty: li.quantity,
          received_qty: li.quantity,
          accepted_qty: li.quantity,
          rejected_qty: 0,
        }))
      );
    } catch (err) {
      setError(err.message);
    }
  };

  const updateLineItem = (index, field, value) => {
    const updated = [...lineItems];
    updated[index] = { ...updated[index], [field]: Number(value) };
    setLineItems(updated);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const data = await api.post('/grns', {
        purchase_order_id: Number(selectedPoId),
        received_date: receivedDate,
        line_items: lineItems.map((li) => ({
          po_line_item_id: li.po_line_item_id,
          received_qty: li.received_qty,
          accepted_qty: li.accepted_qty,
          rejected_qty: li.rejected_qty,
        })),
      });
      const grn = data.grn || data;
      navigate('/grns');
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
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

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Create GRN</h1>
        <p className="page-header__subtitle">Record goods received against a purchase order</p>
      </div>
      <div className="card">
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="grnPo">Purchase Order</label>
              <select id="grnPo" className="form-input" value={selectedPoId} onChange={(e) => handlePoSelect(e.target.value)} required>
                <option value="">Select Purchase Order</option>
                {purchaseOrders.map((po) => (
                  <option key={po.id} value={po.id}>{po.po_number} - {po.vendor_name || po.vendor || ''}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="grnDate">Received Date</label>
              <input id="grnDate" className="form-input" type="date" value={receivedDate} onChange={(e) => setReceivedDate(e.target.value)} required />
            </div>

            {lineItems.length > 0 && (
              <>
                <h3 style={{ marginTop: 24 }}>Line Items</h3>
                <table className="email-table">
                  <thead>
                    <tr>
                      <th>Description</th>
                      <th>Ordered</th>
                      <th>Received</th>
                      <th>Accepted</th>
                      <th>Rejected</th>
                    </tr>
                  </thead>
                  <tbody>
                    {lineItems.map((item, index) => (
                      <tr key={index}>
                        <td>{item.description}</td>
                        <td>{item.ordered_qty}</td>
                        <td>
                          <input className="form-input" type="number" min="0" value={item.received_qty} onChange={(e) => updateLineItem(index, 'received_qty', e.target.value)} style={{ width: 80 }} />
                        </td>
                        <td>
                          <input className="form-input" type="number" min="0" value={item.accepted_qty} onChange={(e) => updateLineItem(index, 'accepted_qty', e.target.value)} style={{ width: 80 }} />
                        </td>
                        <td>
                          <input className="form-input" type="number" min="0" value={item.rejected_qty} onChange={(e) => updateLineItem(index, 'rejected_qty', e.target.value)} style={{ width: 80 }} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </>
            )}

            <div style={{ marginTop: 16 }}>
              <button className="btn btn--primary" type="submit" disabled={submitting || !selectedPoId}>
                {submitting ? 'Creating...' : 'Create GRN'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
