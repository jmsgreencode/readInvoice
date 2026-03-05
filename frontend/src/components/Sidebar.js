import { useEffect, useState } from 'react';
import { NavLink } from 'react-router-dom';
import { api } from '../api';
import { useAuth } from '../context/AuthContext';

export default function Sidebar() {
  const [vendors, setVendors] = useState([]);
  const { hasPermission, isSuperAdmin } = useAuth();

  const can = (perm) => isSuperAdmin() || hasPermission(perm);

  useEffect(() => {
    api.get('/vendors').then((data) => {
      setVendors(data.vendors || []);
    }).catch(() => {});
  }, []);

  return (
    <aside className="sidebar">
      <nav className="sidebar__nav">
        <NavLink to="/dashboard" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
          Dashboard
        </NavLink>

        {can('vendors.view') && (
          <NavLink to="/vendors" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Vendors
          </NavLink>
        )}

        {can('vendors.create') && (
          <NavLink to="/vendors/add" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Add Vendor
          </NavLink>
        )}

        {can('vendor_requests.create') && (
          <NavLink to="/vendor-requests/new" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            New Vendor Request
          </NavLink>
        )}

        {(can('vendor_requests.view_own') || can('vendor_requests.view_all')) && (
          <NavLink to="/vendor-requests" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Vendor Requests
          </NavLink>
        )}

        {can('requisitions.view') && (
          <NavLink to="/requisitions" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Requisitions
          </NavLink>
        )}

        {can('purchase_orders.view') && (
          <NavLink to="/purchase-orders" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Purchase Orders
          </NavLink>
        )}

        {can('grns.view') && (
          <NavLink to="/grns" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Goods Received
          </NavLink>
        )}

        {can('invoices.view') && (
          <NavLink to="/invoices/upload" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Upload Invoice
          </NavLink>
        )}

        {can('matching.view') && (
          <NavLink to="/matching" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            3-Way Match
          </NavLink>
        )}

        {can('budgets.view') && (
          <NavLink to="/budgets" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Budgets
          </NavLink>
        )}

        {can('departments.view') && (
          <NavLink to="/departments" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Departments
          </NavLink>
        )}

        {can('compliance.view') && (
          <NavLink to="/compliance" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Compliance
          </NavLink>
        )}

        {can('staging.view') && (
          <NavLink to="/staging" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Vendor Staging
          </NavLink>
        )}

        {can('reports.view') && (
          <NavLink to="/reports" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Reports
          </NavLink>
        )}

        {can('imports.view') && (
          <NavLink to="/imports" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Imports
          </NavLink>
        )}

        {can('admin.roles') && (
          <NavLink to="/admin/roles" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Roles & Permissions
          </NavLink>
        )}

        {can('admin.users') && (
          <NavLink to="/admin/users" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            User Management
          </NavLink>
        )}

        {can('admin.logs') && (
          <NavLink to="/admin/logs" className={({ isActive }) => `sidebar__nav-item${isActive ? ' sidebar__nav-item--active' : ''}`}>
            Admin Logs
          </NavLink>
        )}
      </nav>

      <div className="sidebar__divider" />
      <div className="sidebar__title">Vendors</div>
      <ul className="sidebar__list">
        {vendors.length === 0 && (
          <li className="sidebar__item" style={{ color: 'var(--color-text-muted)', cursor: 'default' }}>
            No vendors yet
          </li>
        )}
        {vendors.map((v) => (
          <li key={v.id}>
            <NavLink
              to={`/vendors/${v.id}`}
              className={({ isActive }) => `sidebar__item${isActive ? ' sidebar__item--active' : ''}`}
            >
              {v.name}
              {v.email_count != null && (
                <span className="sidebar__item-count">{v.email_count}</span>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </aside>
  );
}
