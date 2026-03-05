import { useEffect, useState } from 'react';
import { api } from '../api';

export default function DepartmentListPage() {
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [managerUserId, setManagerUserId] = useState('');
  const [formError, setFormError] = useState('');
  const [formSuccess, setFormSuccess] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchDepartments = () => {
    setLoading(true);
    api.get('/departments')
      .then((data) => setDepartments(data.departments || data || []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchDepartments();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    setFormSuccess('');
    setSubmitting(true);
    try {
      await api.post('/departments', {
        name,
        code,
        manager_user_id: managerUserId ? Number(managerUserId) : undefined,
      });
      setFormSuccess('Department created successfully.');
      setName('');
      setCode('');
      setManagerUserId('');
      fetchDepartments();
    } catch (err) {
      setFormError(err.message);
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

  if (error) {
    return <div className="alert alert--error">{error}</div>;
  }

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Departments</h1>
        <p className="page-header__subtitle">Manage organizational departments</p>
      </div>

      <div className="card" style={{ maxWidth: 500, marginBottom: 24 }}>
        <div className="card__header">
          <span className="card__title">Add Department</span>
        </div>
        <div className="card__body">
          {formError && <div className="alert alert--error">{formError}</div>}
          {formSuccess && <div className="alert alert--success">{formSuccess}</div>}
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label" htmlFor="deptName">Name</label>
              <input id="deptName" className="form-input" type="text" value={name} onChange={(e) => setName(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="deptCode">Code</label>
              <input id="deptCode" className="form-input" type="text" value={code} onChange={(e) => setCode(e.target.value)} required />
            </div>
            <div className="form-group">
              <label className="form-label" htmlFor="deptManager">Manager User ID</label>
              <input id="deptManager" className="form-input" type="number" value={managerUserId} onChange={(e) => setManagerUserId(e.target.value)} />
            </div>
            <button className="btn btn--primary" type="submit" disabled={submitting}>
              {submitting ? 'Creating...' : 'Create Department'}
            </button>
          </form>
        </div>
      </div>

      <div className="card">
        <div className="card__header">
          <span className="card__title">All Departments</span>
        </div>
        {departments.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No departments found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Manager</th>
              </tr>
            </thead>
            <tbody>
              {departments.map((dept) => (
                <tr key={dept.id}>
                  <td>{dept.name}</td>
                  <td>{dept.code}</td>
                  <td>{dept.manager_user_id || 'N/A'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
