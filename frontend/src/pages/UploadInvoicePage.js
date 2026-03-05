import { useEffect, useState } from 'react';
import { api } from '../api';

export default function UploadInvoicePage() {
  const [vendors, setVendors] = useState([]);
  const [vendorId, setVendorId] = useState('');
  const [file, setFile] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    api.get('/vendors').then((data) => {
      const list = data.vendors || [];
      setVendors(list);
      if (list.length > 0) setVendorId(String(list[0].id));
    }).catch(() => {});
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) { setError('Please select a PDF file'); return; }
    setError('');
    setSuccess('');
    setLoading(true);
    try {
      const formData = new FormData();
      formData.append('vendor_id', vendorId);
      formData.append('file', file);
      await api.postForm('/invoices/upload', formData);
      setSuccess('Invoice uploaded successfully.');
      setFile(null);
      e.target.reset();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Upload Invoice</h1>
        <p className="page-header__subtitle">Upload a PDF invoice for processing</p>
      </div>
      <div className="card" style={{ maxWidth: 500 }}>
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          {success && <div className="alert alert--success">{success}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="vendor">Vendor</label>
              <select
                id="vendor"
                className="form-input"
                value={vendorId}
                onChange={(e) => setVendorId(e.target.value)}
                required
              >
                {vendors.length === 0 && <option value="">No vendors available</option>}
                {vendors.map((v) => (
                  <option key={v.id} value={v.id}>{v.name}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="pdfFile">PDF File</label>
              <input
                id="pdfFile"
                className="form-input"
                type="file"
                accept=".pdf,application/pdf"
                onChange={(e) => setFile(e.target.files[0] || null)}
                required
              />
            </div>
            <button className="btn btn--primary" type="submit" disabled={loading || vendors.length === 0}>
              {loading ? 'Uploading...' : 'Upload Invoice'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
