import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';

export default function CreateRequisitionPage() {
  const navigate = useNavigate();
  const [departments, setDepartments] = useState([]);
  const [vendors, setVendors] = useState([]);
  const [departmentId, setDepartmentId] = useState('');
  const [vendorId, setVendorId] = useState('');
  const [justification, setJustification] = useState('');
  const [priority, setPriority] = useState('normal');
  const [lineItems, setLineItems] = useState([{ description: '', quantity: 1, unit_price: 0 }]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([api.get('/departments'), api.get('/vendors')])
      .then(([d, v]) => {
        setDepartments(d.departments || d || []);
        setVendors(v.vendors || v || []);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  const addLineItem = () => {
    setLineItems([...lineItems, { description: '', quantity: 1, unit_price: 0 }]);
  };

  const removeLineItem = (index) => {
    setLineItems(lineItems.filter((_, i) => i !== index));
  };

  const updateLineItem = (index, field, value) => {
    const updated = [...lineItems];
    updated[index] = { ...updated[index], [field]: value };
    setLineItems(updated);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const data = await api.post('/requisitions', {
        department_id: Number(departmentId),
        vendor_id: Number(vendorId),
        justification,
        priority,
        line_items: lineItems.map((li) => ({
          description: li.description,
          quantity: Number(li.quantity),
          unit_price: Number(li.unit_price),
        })),
      });
      const req = data.requisition || data;
      navigate(`/requisitions/${req.id}`);
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
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

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Create Requisition</h1>
        <p className="page-header__subtitle">Submit a new purchase requisition</p>
      </div>
      <div className="card">
        <div className="card__body">
          {error && <div className="alert alert--error">{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="reqDept">Department</label>
              <select id="reqDept" className="form-input" value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} required>
                <option value="">Select Department</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="reqVendor">Vendor</label>
              <select id="reqVendor" className="form-input" value={vendorId} onChange={(e) => setVendorId(e.target.value)} required>
                <option value="">Select Vendor</option>
                {vendors.map((v) => (
                  <option key={v.id} value={v.id}>{v.name}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="reqJustification">Justification</label>
              <textarea id="reqJustification" className="form-input" rows={4} value={justification} onChange={(e) => setJustification(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="reqPriority">Priority</label>
              <select id="reqPriority" className="form-input" value={priority} onChange={(e) => setPriority(e.target.value)}>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>

            <h3 style={{ marginTop: 24 }}>Line Items</h3>
            {lineItems.map((item, index) => (
              <div key={index} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', marginBottom: 8 }}>
                <div className="form-group" style={{ flex: 3, marginBottom: 0 }}>
                  {index === 0 && <label className="form-label">Description</label>}
                  <input className="form-input" type="text" value={item.description} onChange={(e) => updateLineItem(index, 'description', e.target.value)} required />
                </div>
                <div className="form-group" style={{ flex: 1, marginBottom: 0 }}>
                  {index === 0 && <label className="form-label">Qty</label>}
                  <input className="form-input" type="number" min="1" value={item.quantity} onChange={(e) => updateLineItem(index, 'quantity', e.target.value)} required />
                </div>
                <div className="form-group" style={{ flex: 1, marginBottom: 0 }}>
                  {index === 0 && <label className="form-label">Unit Price</label>}
                  <input className="form-input" type="number" min="0" step="0.01" value={item.unit_price} onChange={(e) => updateLineItem(index, 'unit_price', e.target.value)} required />
                </div>
                <button type="button" className="btn btn--danger btn--sm" onClick={() => removeLineItem(index)} disabled={lineItems.length === 1}>Remove</button>
              </div>
            ))}
            <button type="button" className="btn" onClick={addLineItem} style={{ marginBottom: 16 }}>Add Line Item</button>

            <div>
              <button className="btn btn--primary" type="submit" disabled={submitting}>
                {submitting ? 'Creating...' : 'Create Requisition'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
