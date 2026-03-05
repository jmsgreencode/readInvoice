<?php
/**
 * Invoice detail page (full page view).
 *
 * Variables: $user, $csrfToken, $isAdmin, $pageTitle, $invoice
 */

ob_start();
include __DIR__ . '/Partial/invoice-detail.php';
$innerContent = ob_get_clean();

$content = $innerContent;
include __DIR__ . '/Layout/base.php';
