import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function ImportDetailPage() {
  const { id } = useParams();
  const [importData, setImportData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/imports/${id}`)
      .then((data) => setImportData(data.import || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleRetry = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/imports/${id}/retry`);
      setActionMessage('Import retry initiated.');
      const data = await api.get(`/imports/${id}`);
      setImportData(data.import || data);
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

  if (!importData) return null;

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Import Details</h1>
        <p className="page-header__subtitle">{importData.file_name || ''}</p>
      </div>

      {actionMessage && <div className="alert alert--success" style={{ marginBottom: 16 }}>{actionMessage}</div>}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card__header">
          <span className="card__title">Details</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Batch ID</div>
              <div className="invoice-detail__value">{importData.batch_id || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">File Name</div>
              <div className="invoice-detail__value">{importData.file_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Import Type</div>
              <div className="invoice-detail__value">{importData.import_type || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Status</div>
              <div className="invoice-detail__value"><StatusBadge status={importData.status} /></div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Total Rows</div>
              <div className="invoice-detail__value">{importData.total_rows ?? 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Processed Rows</div>
              <div className="invoice-detail__value">{importData.processed_rows ?? 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Error Rows</div>
              <div className="invoice-detail__value">{importData.error_rows ?? 'N/A'}</div>
            </div>
          </div>
        </div>
      </div>

      {importData.status === 'failed' && (
        <>
          {importData.error_log && (
            <div className="card" style={{ marginBottom: 16 }}>
              <div className="card__header">
                <span className="card__title">Error Log</span>
              </div>
              <div className="card__body">
                <pre style={{ whiteSpace: 'pre-wrap', background: '#f5f5f5', padding: 12, borderRadius: 4 }}>
                  {typeof importData.error_log === 'string' ? importData.error_log : JSON.stringify(importData.error_log, null, 2)}
                </pre>
              </div>
            </div>
          )}
          <button className="btn btn--primary" onClick={handleRetry} disabled={actionLoading}>
            {actionLoading ? 'Retrying...' : 'Retry Import'}
          </button>
        </>
      )}
    </>
  );
}
