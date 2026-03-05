<?php
/**
 * Error toast notification partial.
 *
 * Variables: $errorMessage (string), $errorDetail (string|null, admin only)
 */
?>
<div id="toast-container" class="toast-container">
    <div class="toast toast--error">
        <div class="toast__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>
        <div class="toast__content">
            <div class="toast__title">Error</div>
            <div class="toast__message">
                <?= htmlspecialchars($errorMessage ?? 'An unexpected error occurred.', ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($errorDetail)): ?>
                    <br><small><?= htmlspecialchars($errorDetail, ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
            </div>
        </div>
        <button class="toast__close" onclick="this.closest('.toast').remove()">&times;</button>
    </div>
</div>
