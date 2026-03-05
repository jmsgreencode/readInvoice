import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api';
import StatusBadge from '../components/StatusBadge';

export default function VendorDetailPage() {
  const { id } = useParams();
  const [vendor, setVendor] = useState(null);
  const [documents, setDocuments] = useState([]);
  const [emails, setEmails] = useState([]);
  const [invoices, setInvoices] = useState([]);
  const [activeTab, setActiveTab] = useState('details');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState('');
  const [uploadFile, setUploadFile] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError('');
    Promise.all([
      api.get(`/vendors/${id}`),
      api.get(`/vendors/${id}/documents`),
      api.get(`/vendors/${id}/emails`),
      api.get(`/vendors/${id}/invoices`),
    ])
      .then(([v, d, e, i]) => {
        setVendor(v.vendor || v);
        setDocuments(d.documents || d || []);
        setEmails(e.emails || e || []);
        setInvoices(i.invoices || i || []);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleVerify = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/vendors/${id}/verify`);
      setActionMessage('Vendor verified successfully.');
      setVendor((prev) => ({ ...prev, verification_status: 'verified' }));
    } catch (err) {
      setActionMessage(err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleBlock = async () => {
    setActionLoading(true);
    setActionMessage('');
    try {
      await api.post(`/vendors/${id}/block`);
      setActionMessage('Vendor blocked successfully.');
      setVendor((prev) => ({ ...prev, is_blocked: true }));
    } catch (err) {
      setActionMessage(err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleDocumentUpload = async (e) => {
    e.preventDefault();
    if (!uploadFile) return;
    setActionLoading(true);
    setActionMessage('');
    try {
      const formData = new FormData();
      formData.append('file', uploadFile);
      await api.upload(`/vendors/${id}/documents`, formData);
      setActionMessage('Document uploaded successfully.');
      setUploadFile(null);
      const d = await api.get(`/vendors/${id}/documents`);
      setDocuments(d.documents || d || []);
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

  if (!vendor) return null;

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">{vendor.name}</h1>
        <p className="page-header__subtitle">{vendor.domain || vendor.contact_email || ''}</p>
      </div>

      {actionMessage && <div className="alert alert--success">{actionMessage}</div>}

      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        <button className="btn btn--primary" onClick={handleVerify} disabled={actionLoading}>Verify</button>
        <button className="btn btn--danger" onClick={handleBlock} disabled={actionLoading}>Block</button>
      </div>

      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        {['details', 'documents', 'emails', 'invoices'].map((tab) => (
          <button
            key={tab}
            className={`btn ${activeTab === tab ? 'btn--primary' : ''}`}
            onClick={() => setActiveTab(tab)}
          >
            {tab.charAt(0).toUpperCase() + tab.slice(1)}
          </button>
        ))}
      </div>

      {activeTab === 'details' && (
        <div className="card">
          <div className="card__header">
            <span className="card__title">Vendor Details</span>
          </div>
          <div className="card__body">
            <div className="invoice-detail">
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Name</div>
                <div className="invoice-detail__value">{vendor.name}</div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Domain</div>
                <div className="invoice-detail__value">{vendor.domain || 'N/A'}</div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Verification Status</div>
                <div className="invoice-detail__value"><StatusBadge status={vendor.verification_status} /></div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Risk Rating</div>
                <div className="invoice-detail__value">{vendor.risk_rating || 'N/A'}</div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Effective Date</div>
                <div className="invoice-detail__value">{vendor.effective_date ? new Date(vendor.effective_date).toLocaleDateString() : 'N/A'}</div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Expiry Date</div>
                <div className="invoice-detail__value">{vendor.expiry_date ? new Date(vendor.expiry_date).toLocaleDateString() : 'N/A'}</div>
              </div>
              <div className="invoice-detail__section">
                <div className="invoice-detail__label">Blocked</div>
                <div className="invoice-detail__value">
                  <span className={`badge badge--${vendor.is_blocked ? 'danger' : 'success'}`}>
                    {vendor.is_blocked ? 'Yes' : 'No'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'documents' && (
        <div className="card">
          <div className="card__header">
            <span className="card__title">Documents</span>
          </div>
          <div className="card__body">
            <form onSubmit={handleDocumentUpload} style={{ marginBottom: 16 }}>
              <div className="form-group">
                <label className="form-label" htmlFor="docUpload">Upload Document</label>
                <input
                  id="docUpload"
                  className="form-input"
                  type="file"
                  onChange={(e) => setUploadFile(e.target.files[0])}
                />
              </div>
              <button className="btn btn--primary" type="submit" disabled={actionLoading || !uploadFile}>Upload</button>
            </form>
            {documents.length === 0 ? (
              <div className="empty-state">
                <div className="empty-state__title">No documents</div>
              </div>
            ) : (
              <table className="email-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Uploaded</th>
                  </tr>
                </thead>
                <tbody>
                  {documents.map((doc) => (
                    <tr key={doc.id}>
                      <td>{doc.name || doc.file_name}</td>
                      <td>{doc.type || doc.mime_type || 'N/A'}</td>
                      <td>{doc.created_at ? new Date(doc.created_at).toLocaleDateString() : 'N/A'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {activeTab === 'emails' && (
        <div className="card">
          <div className="card__header">
            <span className="card__title">Emails</span>
          </div>
          {emails.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state__title">No emails found</div>
            </div>
          ) : (
            <table className="email-table">
              <thead>
                <tr>
                  <th>Subject</th>
                  <th>From</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {emails.map((email) => (
                  <tr key={email.id}>
                    <td>{email.subject}</td>
                    <td>{email.from_address || email.sender}</td>
                    <td>{email.received_at ? new Date(email.received_at).toLocaleDateString() : 'N/A'}</td>
                    <td>
                      <span className={`badge badge--${email.processing_status === 'completed' ? 'success' : email.processing_status === 'error' ? 'danger' : 'warning'}`}>
                        {email.processing_status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {activeTab === 'invoices' && (
        <div className="card">
          <div className="card__header">
            <span className="card__title">Invoices</span>
          </div>
          {invoices.length === 0 ? (
            <div className="empty-state">
              <div className="empty-state__title">No invoices found</div>
            </div>
          ) : (
            <table className="email-table">
              <thead>
                <tr>
                  <th>Invoice #</th>
                  <th>Amount</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id}>
                    <td>
                      <Link to={`/invoices/${inv.id}`}>{inv.invoice_number || inv.id}</Link>
                    </td>
                    <td>{inv.total_amount != null ? `$${Number(inv.total_amount).toFixed(2)}` : 'N/A'}</td>
                    <td>{inv.invoice_date ? new Date(inv.invoice_date).toLocaleDateString() : 'N/A'}</td>
                    <td><StatusBadge status={inv.extraction_status || inv.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </>
  );
}
