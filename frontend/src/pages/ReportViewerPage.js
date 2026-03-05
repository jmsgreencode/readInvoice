import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import DataTable from '../components/DataTable';

export default function ReportViewerPage() {
  const { type } = useParams();
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/reports/${type}`)
      .then((data) => setReport(data.report || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [type]);

  const handleExport = (format) => {
    const token = localStorage.getItem('token');
    window.open(`/api/reports/${type}/export?format=${format}&token=${token}`, '_blank');
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

  if (!report) return null;

  const columns = report.columns || [];
  const rows = report.rows || [];

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">{report.name || type}</h1>
        <p className="page-header__subtitle">{report.description || ''}</p>
      </div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        <button className="btn btn--primary btn--sm" onClick={() => handleExport('csv')}>Export CSV</button>
        <button className="btn btn--primary btn--sm" onClick={() => handleExport('xlsx')}>Export XLSX</button>
        <button className="btn btn--primary btn--sm" onClick={() => handleExport('pdf')}>Export PDF</button>
      </div>
      <div className="card">
        <div className="card__body">
          {rows.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state__title">No data</div>
            </div>
          ) : (
            <DataTable columns={columns} rows={rows} />
          )}
        </div>
      </div>
    </>
  );
}
