import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';

export default function BudgetDetailPage() {
  const { id } = useParams();
  const [budget, setBudget] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/budgets/${id}/utilization`)
      .then((data) => setBudget(data.budget || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

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

  if (!budget) return null;

  const pct = budget.utilization_pct != null ? Number(budget.utilization_pct) : 0;
  const gaugeColor = pct > 90 ? '#e74c3c' : pct > 70 ? '#f39c12' : '#27ae60';

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Budget Details</h1>
        <p className="page-header__subtitle">{budget.department_name || ''} - {budget.fiscal_year || ''}</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Utilization</span>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Total Amount</div>
              <div className="invoice-detail__value">${Number(budget.total_amount || 0).toFixed(2)}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Allocated</div>
              <div className="invoice-detail__value">${Number(budget.allocated_amount || 0).toFixed(2)}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Spent</div>
              <div className="invoice-detail__value">${Number(budget.spent_amount || 0).toFixed(2)}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Utilization</div>
              <div className="invoice-detail__value">{pct.toFixed(1)}%</div>
            </div>
          </div>

          <div style={{ marginTop: 24 }}>
            <div style={{ background: '#e0e0e0', borderRadius: 8, height: 32, width: '100%', overflow: 'hidden' }}>
              <div
                style={{
                  background: gaugeColor,
                  height: '100%',
                  width: `${Math.min(pct, 100)}%`,
                  borderRadius: 8,
                  transition: 'width 0.3s ease',
                }}
              />
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
