<?php
/**
 * Invoice detail card partial.
 *
 * Variables: $invoice (array), $isAdmin (bool)
 *
 * Shows vendor name, invoice number, dates, amounts, extracted text, PDF download link.
 */
$e = function (mixed $val): string {
    return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<div id="main-content-area">
    <div class="page-header">
        <h1 class="page-header__title">Invoice #<?= $e($invoice['invoice_number'] ?? $invoice['id'] ?? '--') ?></h1>
        <p class="page-header__subtitle">
            <?= $e($invoice['vendor_name'] ?? 'Unknown Vendor') ?>
            <?php if (!empty($invoice['invoice_date'])): ?>
                &middot; <?= $e(date('M j, Y', strtotime($invoice['invoice_date']))) ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card__header">
            <h2 class="card__title">Invoice Details</h2>
            <div>
                <?php if (!empty($invoice['id'])): ?>
                    <a href="/invoices/<?= (int) $invoice['id'] ?>/download" class="btn btn--secondary btn--sm" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Download PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card__body">
            <div class="invoice-detail">
                <!-- Vendor -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Vendor</div>
                    <div class="invoice-detail__value"><?= $e($invoice['vendor_name']) ?></div>
                </div>

                <!-- Invoice Number -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Invoice Number</div>
                    <div class="invoice-detail__value"><?= $e($invoice['invoice_number'] ?? '--') ?></div>
                </div>

                <!-- Invoice Date -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Invoice Date</div>
                    <div class="invoice-detail__value">
                        <?= !empty($invoice['invoice_date'])
                            ? $e(date('F j, Y', strtotime($invoice['invoice_date'])))
                            : '--' ?>
                    </div>
                </div>

                <!-- Due Date -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Due Date</div>
                    <div class="invoice-detail__value">
                        <?= !empty($invoice['due_date'])
                            ? $e(date('F j, Y', strtotime($invoice['due_date'])))
                            : '--' ?>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Total Amount</div>
                    <div class="invoice-detail__amount">
                        <?php if (isset($invoice['total_amount'])): ?>
                            $<?= $e(number_format((float) $invoice['total_amount'], 2)) ?>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Currency -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Currency</div>
                    <div class="invoice-detail__value"><?= $e($invoice['currency'] ?? 'USD') ?></div>
                </div>

                <!-- Status -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Status</div>
                    <div class="invoice-detail__value">
                        <?php
                            $status = $invoice['status'] ?? 'unknown';
                            $badgeClass = match ($status) {
                                'extracted', 'completed' => 'badge--success',
                                'processing', 'pending' => 'badge--warning',
                                'failed', 'error' => 'badge--danger',
                                default => 'badge--neutral',
                            };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $e(ucfirst($status)) ?></span>
                    </div>
                </div>

                <!-- Processing Date -->
                <div class="invoice-detail__section">
                    <div class="invoice-detail__label">Processed At</div>
                    <div class="invoice-detail__value">
                        <?= !empty($invoice['processed_at'])
                            ? $e(date('M j, Y g:i A', strtotime($invoice['processed_at'])))
                            : '--' ?>
                    </div>
                </div>

                <!-- Extracted Text -->
                <?php if (!empty($invoice['extracted_text'])): ?>
                    <div class="invoice-detail__section" style="grid-column: 1 / -1;">
                        <div class="invoice-detail__label">Extracted Text (OCR)</div>
                        <div class="invoice-detail__extracted-text"><?= $e($invoice['extracted_text']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($isAdmin && !empty($invoice['raw_data'])): ?>
                    <div class="invoice-detail__section" style="grid-column: 1 / -1;">
                        <div class="invoice-detail__label">Raw Data (Admin Only)</div>
                        <div class="invoice-detail__extracted-text"><?= $e(
                            is_string($invoice['raw_data'])
                                ? $invoice['raw_data']
                                : json_encode($invoice['raw_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        ) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Back button -->
    <?php if (!empty($invoice['vendor_id'])): ?>
        <button
            class="btn btn--secondary"
            data-on-click="$$get('/vendors/<?= (int) $invoice['vendor_id'] ?>/emails')"
            data-indicator
        >
            &larr; Back to Vendor Emails
        </button>
    <?php endif; ?>
</div>
