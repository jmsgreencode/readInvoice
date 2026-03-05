import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function CreateVendorRequestPage() {
  const navigate = useNavigate();
  const [vendorName, setVendorName] = useState('');
  const [vendorWebsite, setVendorWebsite] = useState('');
  const [contactName, setContactName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [justification, setJustification] = useState('');
  const [category, setCategory] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const data = await api.post('/vendor-requests', {
        vendor_name: vendorName,
        vendor_website: vendorWebsite,
        vendor_contact_name: contactName,
        vendor_contact_email: contactEmail,
        vendor_contact_phone: contactPhone,
        business_justification: justification,
        category,
      });
      const req = data.vendor_request || data.request || data;
      await api.post(`/vendor-requests/${req.id}/submit`);
      navigate(`/vendor-requests/${req.id}`);
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">New Vendor Request</h1>
        <p className="page-header__subtitle">Request a new vendor to be onboarded</p>
      </div>
      <div className="card" style={{ maxWidth: 600 }}>
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="vrName">Vendor Name</label>
              <input id="vrName" className="form-input" type="text" value={vendorName} onChange={(e) => setVendorName(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrWebsite">Vendor Website</label>
              <input id="vrWebsite" className="form-input" type="url" value={vendorWebsite} onChange={(e) => setVendorWebsite(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrContactName">Contact Name</label>
              <input id="vrContactName" className="form-input" type="text" value={contactName} onChange={(e) => setContactName(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrContactEmail">Contact Email</label>
              <input id="vrContactEmail" className="form-input" type="email" value={contactEmail} onChange={(e) => setContactEmail(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrContactPhone">Contact Phone</label>
              <input id="vrContactPhone" className="form-input" type="tel" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrJustification">Business Justification</label>
              <textarea id="vrJustification" className="form-input" rows={4} value={justification} onChange={(e) => setJustification(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="vrCategory">Category</label>
              <input id="vrCategory" className="form-input" type="text" value={category} onChange={(e) => setCategory(e.target.value)} />
            </div>
            <button className="btn btn--primary" type="submit" disabled={submitting}>
              {submitting ? 'Submitting...' : 'Submit Request'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
