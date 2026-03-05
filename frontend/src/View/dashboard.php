<?php
/**
 * Dashboard page.
 *
 * Uses Datastar data-on-load to automatically fetch the vendor list on page load.
 *
 * Variables: $user, $csrfToken, $isAdmin, $pageTitle
 */

// Build inner content
ob_start();
?>
<div id="main-content-area" data-on-load="$$get('/vendors')">
    <div class="page-header">
        <h1 class="page-header__title">Invoice Dashboard</h1>
        <p class="page-header__subtitle">Select a vendor from the sidebar to view their emails and invoices.</p>
    </div>

    <div class="card">
        <div class="card__body">
            <div class="empty-state">
                <div class="empty-state__icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                </div>
                <div class="empty-state__title">Welcome to ReadInvoice</div>
                <div class="empty-state__description">
                    Choose a vendor from the left sidebar to browse their emails and extracted invoice data.
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$innerContent = ob_get_clean();

// Wrap in base layout
$content = $innerContent;
include __DIR__ . '/Layout/base.php';
