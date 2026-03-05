import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function ImportUploadPage() {
  const navigate = useNavigate();
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) return;
    setError('');
    setResult(null);
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const data = await api.upload('/staging/import', formData);
      setResult(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  };

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Import Vendors</h1>
        <p className="page-header__subtitle">Upload a CSV file to import vendor records</p>
      </div>
      <div className="card" style={{ maxWidth: 500 }}>
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          {result && (
            <div className="alert alert--success">
              Import completed. {result.total_rows != null && `Total rows: ${result.total_rows}.`} {result.imported != null && `Imported: ${result.imported}.`} {result.errors != null && `Errors: ${result.errors}.`}
            </div>
          )}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="csvFile">CSV File</label>
              <input
                id="csvFile"
                className="form-input"
                type="file"
                accept=".csv"
                onChange={(e) => setFile(e.target.files[0])}
                required
              />
            </div>
            <button className="btn btn--primary" type="submit" disabled={uploading || !file}>
              {uploading ? 'Uploading...' : 'Upload'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
