import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';

export default function InvoiceDetailPage() {
  const { id } = useParams();
  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    api.get(`/invoices/${id}`)
      .then((data) => setInvoice(data.invoice || data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  const handleDownload = async () => {
    const token = localStorage.getItem('token');
    const res = await fetch(`/api/invoices/${id}/pdf`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!res.ok) return;
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = invoice?.pdf_original_name || `invoice-${id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
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

  if (!invoice) return null;

  return (
    <>
      <div className="page-header">
        <h1 className="page-header__title">Invoice #{invoice.id}</h1>
        <p className="page-header__subtitle">{invoice.vendor_name || ''}</p>
      </div>
      <div className="card">
        <div className="card__header">
          <span className="card__title">Invoice Details</span>
          <button className="btn btn--primary btn--sm" onClick={handleDownload}>
            Download PDF
          </button>
        </div>
        <div className="card__body">
          <div className="invoice-detail">
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Invoice Number</div>
              <div className="invoice-detail__value">{invoice.invoice_number || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Amount</div>
              <div className="invoice-detail__amount">
                {invoice.total_amount != null ? `$${Number(invoice.total_amount).toFixed(2)}` : 'N/A'}
              </div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Date</div>
              <div className="invoice-detail__value">
                {invoice.invoice_date ? new Date(invoice.invoice_date).toLocaleDateString() : 'N/A'}
              </div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Status</div>
              <div className="invoice-detail__value">
                <span className={`badge badge--${invoice.extraction_status === 'completed' ? 'success' : invoice.extraction_status === 'error' ? 'danger' : 'warning'}`}>
                  {invoice.extraction_status}
                </span>
              </div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Vendor</div>
              <div className="invoice-detail__value">{invoice.vendor_name || 'N/A'}</div>
            </div>
            <div className="invoice-detail__section">
              <div className="invoice-detail__label">Uploaded</div>
              <div className="invoice-detail__value">
                {invoice.created_at ? new Date(invoice.created_at).toLocaleString() : 'N/A'}
              </div>
            </div>
            {invoice.ocr_raw_text && (
              <div className="invoice-detail__extracted-text">
                {invoice.ocr_raw_text}
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
