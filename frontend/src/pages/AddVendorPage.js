import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function AddVendorPage() {
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [emailAddress, setEmailAddress] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);
    try {
      const data = await api.post('/vendors', { name, contact_email: emailAddress });
      const vendor = data.vendor || data;
      setSuccess(`Vendor "${vendor.name || name}" created successfully.`);
      setName('');
      setEmailAddress('');
      setTimeout(() => navigate(`/vendors/${vendor.id}/emails`), 1000);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Add Vendor</h1>
        <p className="page-header__subtitle">Register a new vendor to track their invoices</p>
      </div>
      <div className="card" style={{ maxWidth: 500 }}>
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          {success && <div className="alert alert--success">{success}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="vendorName">Vendor Name</label>
              <input
                id="vendorName"
                className="form-input"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Acme Corp"
                required
              />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vendorEmail">Email Address</label>
              <input
                id="vendorEmail"
                className="form-input"
                type="email"
                value={emailAddress}
                onChange={(e) => setEmailAddress(e.target.value)}
                placeholder="vendor@example.com"
                required
              />
            </div>
            <button className="btn btn--primary" type="submit" disabled={loading}>
              {loading ? 'Creating...' : 'Create Vendor'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
