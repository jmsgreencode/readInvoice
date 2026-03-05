<?php
/**
 * Admin layout - extends base layout with admin log panel.
 *
 * Variables expected: $pageTitle, $user, $isAdmin, $csrfToken, $content
 */

// Force admin flag
$isAdmin = true;

// Include the base layout (which conditionally includes the admin panel)
include __DIR__ . '/base.php';
