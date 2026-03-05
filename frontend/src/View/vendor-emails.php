<?php
/**
 * Vendor emails page (full page view).
 *
 * Variables: $user, $csrfToken, $isAdmin, $pageTitle, $vendor, $emails, $vendorId
 */

ob_start();
include __DIR__ . '/Partial/email-table.php';
$innerContent = ob_get_clean();

$content = $innerContent;
include __DIR__ . '/Layout/base.php';
