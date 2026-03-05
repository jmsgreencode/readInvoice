import { useAuth } from '../context/AuthContext';

export default function PermissionGate({ permission, children, fallback = null }) {
  const { hasPermission, isSuperAdmin } = useAuth();

  if (isSuperAdmin() || hasPermission(permission)) {
    return children;
  }
  return fallback;
}
