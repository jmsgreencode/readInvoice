<?php
/**
 * Vendor sidebar list partial.
 *
 * Variables: $vendors (array of vendor objects)
 *
 * Each vendor is clickable and triggers a Datastar SSE request to load their emails.
 */
?>
<div id="vendor-list">
    <?php if (empty($vendors)): ?>
        <div class="empty-state" style="padding: 1.5rem;">
            <div class="empty-state__title">No vendors found</div>
            <div class="empty-state__description">Vendors will appear here once emails are processed.</div>
        </div>
    <?php else: ?>
        <ul class="sidebar__list">
            <?php foreach ($vendors as $vendor): ?>
                <li
                    class="sidebar__item"
                    data-on-click="$$get('/vendors/<?= (int) $vendor['id'] ?>/emails')"
                    data-indicator
                    role="button"
                    tabindex="0"
                    data-on-keydown.enter="$$get('/vendors/<?= (int) $vendor['id'] ?>/emails')"
                >
                    <span><?= htmlspecialchars($vendor['name'] ?? 'Unknown Vendor', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (isset($vendor['email_count'])): ?>
                        <span class="sidebar__item-count"><?= (int) $vendor['email_count'] ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
