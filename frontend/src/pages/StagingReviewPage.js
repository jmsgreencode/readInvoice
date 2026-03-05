import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function StagingReviewPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [record, setRecord] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/staging/${id}`)
      .then((data) => setRecord(data.record || data.staging || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handlePromote = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/staging/${id}/promote`);
      setActionMessage('Vendor promoted successfully.');
      setTimeout(() => navigate('/staging'), 1000);
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

  if (!record) return null;

  const validationErrors = record.validation_errors || [];

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Staging Review</h1>
        <p className="page-header__subtitle">{record.name || ''}</p>
      </div>

      {actionMessage && <div className="alert alert--success" style={{ marginBottom: 16 }}>{actionMessage}</div>}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Staged Vendor Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Name</div>
              <div className="invoice-detail__value">{record.name}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Domain</div>
              <div className="invoice-detail__value">{record.domain || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Batch ID</div>
              <div className="invoice-detail__value">{record.batch_id || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Validation Status</div>
              <div className="invoice-detail__value"><StatusBadge status={record.validation_status} /></div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Promoted Vendor ID</div>
              <div className="invoice-detail__value">{record.promoted_vendor_id || 'Not promoted'}</div>
            </div>
          </div>
        </div>
      </div>

      {validationErrors.length > 0 && (
        <div className="card" style={{ marginBottom: 16 }}>
          <div className="card__header">
            <span className="card__title">Validation Errors</span>
          </div>
          <div className="card__body">
            <ul>
              {validationErrors.map((err, i) => (
                <li key={i} style={{ color: '#e74c3c' }}>{err.message || err}</li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {!record.promoted_vendor_id && (
        <button className="btn btn--primary" onClick={handlePromote} disabled={actionLoading}>
          {actionLoading ? 'Promoting...' : 'Promote to Vendor'}
        </button>
      )}
    </>
  );
}
