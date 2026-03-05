import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';

// Existing pages
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import VendorEmailsPage from './pages/VendorEmailsPage';
import InvoiceDetailPage from './pages/InvoiceDetailPage';
import AddVendorPage from './pages/AddVendorPage';
import UploadInvoicePage from './pages/UploadInvoicePage';
import AdminLogsPage from './pages/AdminLogsPage';

// New pages - Vendors
import VendorListPage from './pages/VendorListPage';
import VendorDetailPage from './pages/VendorDetailPage';

// New pages - Departments & Budgets
import DepartmentListPage from './pages/DepartmentListPage';
import BudgetListPage from './pages/BudgetListPage';
import BudgetDetailPage from './pages/BudgetDetailPage';

// New pages - Requisitions
import RequisitionListPage from './pages/RequisitionListPage';
import CreateRequisitionPage from './pages/CreateRequisitionPage';
import RequisitionDetailPage from './pages/RequisitionDetailPage';

// New pages - Purchase Orders
import PurchaseOrderListPage from './pages/PurchaseOrderListPage';
import PurchaseOrderDetailPage from './pages/PurchaseOrderDetailPage';

// New pages - GRNs
import GrnListPage from './pages/GrnListPage';
import CreateGrnPage from './pages/CreateGrnPage';

// New pages - Matching
import MatchDashboardPage from './pages/MatchDashboardPage';
import MatchResultPage from './pages/MatchResultPage';

// New pages - Compliance
import ComplianceDashboardPage from './pages/ComplianceDashboardPage';
import ComplianceSettingsPage from './pages/ComplianceSettingsPage';

// New pages - Staging
import VendorStagingListPage from './pages/VendorStagingListPage';
import ImportUploadPage from './pages/ImportUploadPage';
import StagingReviewPage from './pages/StagingReviewPage';

// New pages - RBAC Admin
import RolesPage from './pages/RolesPage';
import RoleEditPage from './pages/RoleEditPage';
import UserManagementPage from './pages/UserManagementPage';
import UserEditPage from './pages/UserEditPage';

// New pages - Vendor Requests
import VendorRequestListPage from './pages/VendorRequestListPage';
import CreateVendorRequestPage from './pages/CreateVendorRequestPage';
import VendorRequestDetailPage from './pages/VendorRequestDetailPage';

// New pages - Reports & Imports
import ReportCenterPage from './pages/ReportCenterPage';
import ReportViewerPage from './pages/ReportViewerPage';
import ImportListPage from './pages/ImportListPage';
import ImportDetailPage from './pages/ImportDetailPage';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }
          >
            {/* Dashboard */}
            <Route path="/dashboard" element={<DashboardPage />} />

            {/* Vendors */}
            <Route path="/vendors" element={<VendorListPage />} />
            <Route path="/vendors/add" element={<AddVendorPage />} />
            <Route path="/vendors/:id" element={<VendorDetailPage />} />
            <Route path="/vendors/:id/emails" element={<VendorEmailsPage />} />

            {/* Vendor Requests */}
            <Route path="/vendor-requests" element={<VendorRequestListPage />} />
            <Route path="/vendor-requests/new" element={<CreateVendorRequestPage />} />
            <Route path="/vendor-requests/:id" element={<VendorRequestDetailPage />} />

            {/* Departments & Budgets */}
            <Route path="/departments" element={<DepartmentListPage />} />
            <Route path="/budgets" element={<BudgetListPage />} />
            <Route path="/budgets/:id" element={<BudgetDetailPage />} />

            {/* Requisitions */}
            <Route path="/requisitions" element={<RequisitionListPage />} />
            <Route path="/requisitions/new" element={<CreateRequisitionPage />} />
            <Route path="/requisitions/:id" element={<RequisitionDetailPage />} />

            {/* Purchase Orders */}
            <Route path="/purchase-orders" element={<PurchaseOrderListPage />} />
            <Route path="/purchase-orders/:id" element={<PurchaseOrderDetailPage />} />

            {/* GRNs */}
            <Route path="/grns" element={<GrnListPage />} />
            <Route path="/grns/new" element={<CreateGrnPage />} />

            {/* Invoices */}
            <Route path="/invoices/:id" element={<InvoiceDetailPage />} />
            <Route path="/invoices/upload" element={<UploadInvoicePage />} />

            {/* Matching */}
            <Route path="/matching" element={<MatchDashboardPage />} />
            <Route path="/matching/:id" element={<MatchResultPage />} />

            {/* Compliance */}
            <Route path="/compliance" element={<ComplianceDashboardPage />} />
            <Route path="/compliance/settings" element={<ComplianceSettingsPage />} />

            {/* Staging / Migration */}
            <Route path="/staging" element={<VendorStagingListPage />} />
            <Route path="/staging/import" element={<ImportUploadPage />} />
            <Route path="/staging/:id" element={<StagingReviewPage />} />

            {/* Reports */}
            <Route path="/reports" element={<ReportCenterPage />} />
            <Route path="/reports/:type" element={<ReportViewerPage />} />

            {/* Imports */}
            <Route path="/imports" element={<ImportListPage />} />
            <Route path="/imports/:id" element={<ImportDetailPage />} />

            {/* Admin */}
            <Route path="/admin/roles" element={<RolesPage />} />
            <Route path="/admin/roles/:id" element={<RoleEditPage />} />
            <Route path="/admin/users" element={<UserManagementPage />} />
            <Route path="/admin/users/:id" element={<UserEditPage />} />
            <Route path="/admin/logs" element={<AdminLogsPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
