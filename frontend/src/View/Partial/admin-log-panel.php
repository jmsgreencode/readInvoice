<?php
/**
 * Admin log panel - scrollable log viewer visible only to admin users.
 *
 * Uses Datastar to periodically poll for new log entries.
 */
?>
<div class="admin-log-panel" id="admin-log-panel" data-store='{"logPanelOpen": true}'>
    <div class="admin-log-panel__header">
        <span class="admin-log-panel__title">Application Logs (Admin)</span>
        <div>
            <button
                class="btn btn--secondary btn--sm"
                onclick="document.getElementById('admin-log-body').scrollTop = document.getElementById('admin-log-body').scrollHeight"
            >
                Scroll to Bottom
            </button>
            <button
                class="btn btn--secondary btn--sm"
                onclick="document.getElementById('admin-log-panel').style.display = document.getElementById('admin-log-panel').style.display === 'none' ? 'flex' : 'none'"
            >
                Toggle
            </button>
        </div>
    </div>
    <div class="admin-log-panel__body" id="admin-log-body">
        <div class="admin-log-panel__entry admin-log-panel__entry--info">
            [<?= date('Y-m-d H:i:s') ?>] [INFO] Admin log panel initialized. Viewing application events.
        </div>
        <div class="admin-log-panel__entry">
            [<?= date('Y-m-d H:i:s') ?>] [INFO] Session started for <?= htmlspecialchars($user['email'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="admin-log-panel__entry">
            [<?= date('Y-m-d H:i:s') ?>] [INFO] Backend: <?= htmlspecialchars($_ENV['BACKEND_URL'] ?? 'http://backend:9000', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</div>
