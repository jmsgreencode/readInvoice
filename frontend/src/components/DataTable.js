import { useState } from 'react';

export default function DataTable({ columns, rows, onRowClick }) {
  const [sortCol, setSortCol] = useState(null);
  const [sortDir, setSortDir] = useState('asc');

  const handleSort = (col) => {
    if (sortCol === col) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      setSortCol(col);
      setSortDir('asc');
    }
  };

  const sorted = sortCol ? [...rows].sort((a, b) => {
    const av = a[sortCol] ?? '';
    const bv = b[sortCol] ?? '';
    const cmp = String(av).localeCompare(String(bv), undefined, { numeric: true });
    return sortDir === 'asc' ? cmp : -cmp;
  }) : rows;

  return (
    <div style={{ overflowX: 'auto' }}>
      <table className="table">
        <thead>
          <tr>
            {columns.map(col => (
              <th key={col.key} onClick={() => handleSort(col.key)} style={{ cursor: 'pointer', userSelect: 'none' }}>
                {col.label} {sortCol === col.key ? (sortDir === 'asc' ? '▲' : '▼') : ''}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {sorted.length === 0 && (
            <tr><td colSpan={columns.length} style={{ textAlign: 'center', padding: '2rem', color: 'var(--color-text-muted)' }}>No data</td></tr>
          )}
          {sorted.map((row, i) => (
            <tr key={row.id || i} onClick={() => onRowClick?.(row)} style={onRowClick ? { cursor: 'pointer' } : undefined}>
              {columns.map(col => (
                <td key={col.key}>{col.render ? col.render(row[col.key], row) : row[col.key]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
