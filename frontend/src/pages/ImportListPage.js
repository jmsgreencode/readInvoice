import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function ImportListPage() {
  const [imports, setImports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/imports')
      .then((data) => setImports(data.imports || data || []))
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
        <h1 className="page-header__title">Imports</h1>
        <p className="page-header__subtitle">Track data import jobs</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Imports</span>
        </div>
        {imports.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No imports found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Batch ID</th>
                <th>File Name</th>
                <th>Type</th>
                <th>Status</th>
                <th>Total Rows</th>
                <th>Processed</th>
                <th>Errors</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {imports.map((imp) => (
                <tr key={imp.id}>
                  <td>{imp.batch_id}</td>
                  <td>{imp.file_name}</td>
                  <td>{imp.import_type || 'N/A'}</td>
                  <td><StatusBadge status={imp.status} /></td>
                  <td>{imp.total_rows ?? 'N/A'}</td>
                  <td>{imp.processed_rows ?? 'N/A'}</td>
                  <td>{imp.error_rows ?? 'N/A'}</td>
                  <td><Link to={`/imports/${imp.id}`} className="btn btn--primary btn--sm">View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
