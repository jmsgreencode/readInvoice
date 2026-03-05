<?php
/**
 * Email table partial for a vendor.
 *
 * Variables: $emails (array), $vendor (array), $vendorId (int)
 *
 * Shows subject, date, invoice status, and attachment indicator.
 * Clicking a row with an invoice triggers Datastar SSE to load invoice detail.
 */
?>
<div id="main-content-area">
    <div class="page-header">
        <h1 class="page-header__title"><?= htmlspecialchars($vendor['name'] ?? 'Vendor', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="page-header__subtitle"><?= count($emails) ?> email<?= count($emails) !== 1 ? 's' : '' ?> found</p>
    </div>

    <?php if (empty($emails)): ?>
        <div class="card">
            <div class="card__body">
                <div class="empty-state">
                    <div class="empty-state__icon">&#9993;</div>
                    <div class="empty-state__title">No emails found</div>
                    <div class="empty-state__description">No processed emails from this vendor yet.</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="email-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Invoice Status</th>
                        <th>Attachments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                        <tr
                            <?php if (!empty($email['invoice_id'])): ?>
                                data-on-click="$$get('/invoices/<?= (int) $email['invoice_id'] ?>')"
                                data-indicator
                                style="cursor: pointer;"
                            <?php endif; ?>
                        >
                            <td>
                                <strong><?= htmlspecialchars($email['subject'] ?? '(No subject)', ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($email['sender'])): ?>
                                    <br><small style="color: var(--color-text-muted);"><?= htmlspecialchars($email['sender'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($email['received_at'])): ?>
                                    <?= htmlspecialchars(
                                        date('M j, Y g:i A', strtotime($email['received_at'])),
                                        ENT_QUOTES, 'UTF-8'
                                    ) ?>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted);">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status = $email['invoice_status'] ?? 'none';
                                    $badgeClass = match ($status) {
                                        'extracted' => 'badge--success',
                                        'processing' => 'badge--warning',
                                        'failed' => 'badge--danger',
                                        default => 'badge--neutral',
                                    };
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($email['has_attachments'])): ?>
                                    <svg class="attachment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                    </svg>
                                    <span style="font-size: 0.8rem; color: var(--color-text-secondary);">
                                        <?= (int) ($email['attachment_count'] ?? 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted);">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($email['invoice_id'])): ?>
                                    <button
                                        class="btn btn--primary btn--sm"
                                        data-on-click="$$get('/invoices/<?= (int) $email['invoice_id'] ?>')"
                                        data-indicator
                                    >
                                        View Invoice
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge--neutral">No Invoice</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
