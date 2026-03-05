import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function MatchResultPage() {
  const { id } = useParams();
  const [match, setMatch] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/matching/${id}`)
      .then((data) => setMatch(data.match || data))
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

  if (!match) return null;

  const discrepancy = (a, b) => {
    if (a == null || b == null) return false;
    return Number(a) !== Number(b);
  };

  const amountStyle = (val, ref) => discrepancy(val, ref) ? { color: '#e74c3c', fontWeight: 'bold' } : {};

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Match Result #{match.id}</h1>
        <p className="page-header__subtitle"><StatusBadge status={match.status} /></p>
      </div>

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Amount Comparison</span>
        </div>
        <div className="card__body">
          <table className="email-table">
            <thead>
              <tr>
                <th>Source</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Purchase Order</td>
                <td style={amountStyle(match.po_amount, match.invoice_amount)}>
                  {match.po_amount != null ? `$${Number(match.po_amount).toFixed(2)}` : 'N/A'}
                </td>
              </tr>
              <tr>
                <td>GRN</td>
                <td style={amountStyle(match.grn_amount, match.po_amount)}>
                  {match.grn_amount != null ? `$${Number(match.grn_amount).toFixed(2)}` : 'N/A'}
                </td>
              </tr>
              <tr>
                <td>Invoice</td>
                <td style={amountStyle(match.invoice_amount, match.po_amount)}>
                  {match.invoice_amount != null ? `$${Number(match.invoice_amount).toFixed(2)}` : 'N/A'}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="card">
        <div className="card__header">
          <span className="card__title">Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">PO Number</div>
              <div className="invoice-detail__value">{match.po_number || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Invoice Number</div>
              <div className="invoice-detail__value">{match.invoice_number || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">GRN Number</div>
              <div className="invoice-detail__value">{match.grn_number || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Tolerance</div>
              <div className="invoice-detail__value">{match.tolerance != null ? `${match.tolerance}%` : 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Matched At</div>
              <div className="invoice-detail__value">{match.matched_at ? new Date(match.matched_at).toLocaleString() : 'N/A'}</div>
            </div>
          </div>
          {match.discrepancies && match.discrepancies.length > 0 && (
            <div style={{ marginTop: 16 }}>
              <h4>Discrepancies</h4>
              <ul>
                {match.discrepancies.map((d, i) => (
                  <li key={i} style={{ color: '#e74c3c' }}>{d.message || d}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>
    </>
  );
}
