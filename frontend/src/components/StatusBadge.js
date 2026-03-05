const STATUS_COLORS = {
  matched: 'var(--color-success, #22c55e)',
  approved: 'var(--color-success, #22c55e)',
  verified: 'var(--color-success, #22c55e)',
  completed: 'var(--color-success, #22c55e)',
  active: 'var(--color-success, #22c55e)',
  sent: 'var(--color-success, #22c55e)',
  confirmed: 'var(--color-success, #22c55e)',
  issued: 'var(--color-info, #3b82f6)',
  submitted: 'var(--color-info, #3b82f6)',
  processing: 'var(--color-info, #3b82f6)',
  under_review: 'var(--color-info, #3b82f6)',
  partially_received: 'var(--color-info, #3b82f6)',
  pending: 'var(--color-warning, #f59e0b)',
  draft: 'var(--color-text-muted, #9ca3af)',
  warning: 'var(--color-warning, #f59e0b)',
  mismatched: 'var(--color-error, #ef4444)',
  rejected: 'var(--color-error, #ef4444)',
  failed: 'var(--color-error, #ef4444)',
  critical: 'var(--color-error, #ef4444)',
  blocked: 'var(--color-error, #ef4444)',
  suspended: 'var(--color-error, #ef4444)',
  cancelled: 'var(--color-text-muted, #9ca3af)',
};

export default function StatusBadge({ status }) {
  const color = STATUS_COLORS[status] || 'var(--color-text-muted, #9ca3af)';
  return (
    <span style={{
      display: 'inline-block', padding: '2px 8px', borderRadius: '4px',
      fontSize: '0.75rem', fontWeight: 600, color: '#fff',
      backgroundColor: color, textTransform: 'uppercase',
    }}>
      {(status || '').replace(/_/g, ' ')}
    </span>
  );
}
