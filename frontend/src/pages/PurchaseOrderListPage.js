import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function PurchaseOrderListPage() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/purchase-orders')
      .then((data) => setOrders(data.purchase_orders || data || []))
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
        <h1 className="page-header__title">Purchase Orders</h1>
        <p className="page-header__subtitle">Manage purchase orders</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Purchase Orders</span>
        </div>
        {orders.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No purchase orders found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>PO Number</th>
                <th>Vendor</th>
                <th>Status</th>
                <th>Total Amount</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((po) => (
                <tr
                  key={po.id}
                  style={{ cursor: 'pointer' }}
                  onClick={() => navigate(`/purchase-orders/${po.id}`)}
                >
                  <td>{po.po_number}</td>
                  <td>{po.vendor_name || po.vendor || 'N/A'}</td>
                  <td><StatusBadge status={po.status} /></td>
                  <td>{po.total_amount != null ? `$${Number(po.total_amount).toFixed(2)}` : 'N/A'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
