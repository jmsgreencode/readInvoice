import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function BudgetListPage() {
  const [budgets, setBudgets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get('/budgets')
      .then((data) => setBudgets(data.budgets || data || []))
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
        <h1 className="page-header__title">Budgets</h1>
        <p className="page-header__subtitle">Department budget overview</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">All Budgets</span>
        </div>
        {budgets.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__title">No budgets found</div>
          </div>
        ) : (
          <table className="email-table">
            <thead>
              <tr>
                <th>Department</th>
                <th>Fiscal Year</th>
                <th>Total Amount</th>
                <th>Allocated</th>
                <th>Spent</th>
                <th>Utilization</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {budgets.map((budget) => {
                const utilization = budget.total_amount > 0
                  ? ((budget.spent_amount / budget.total_amount) * 100).toFixed(1)
                  : '0.0';
                return (
                  <tr key={budget.id}>
                    <td>{budget.department_name}</td>
                    <td>{budget.fiscal_year}</td>
                    <td>${Number(budget.total_amount).toFixed(2)}</td>
                    <td>${Number(budget.allocated_amount).toFixed(2)}</td>
                    <td>${Number(budget.spent_amount).toFixed(2)}</td>
                    <td>{utilization}%</td>
                    <td><Link to={`/budgets/${budget.id}`} className="btn btn--primary btn--sm">View</Link></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
