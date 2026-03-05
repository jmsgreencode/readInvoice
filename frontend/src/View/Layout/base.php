<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle ?? 'ReadInvoice', ENT_QUOTES, 'UTF-8') ?> - ReadInvoice</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script type="module" src="https://cdn.jsdelivr.net/gh/starfederation/datastar@v1/bundles/datastar.js"></script>
</head>
<body class="<?= !empty($isAdmin) ? 'has-admin-panel' : '' ?>">

    <!-- Header -->
    <header class="app-header">
        <div class="app-header__brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            ReadInvoice
        </div>
        <div class="app-header__actions">
            <?php if (!empty($user)): ?>
                <span class="app-header__user">
                    <?= htmlspecialchars($user['name'] ?? $user['email'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($isAdmin)): ?>
                        <span class="badge badge--warning">Admin</span>
                    <?php endif; ?>
                </span>
                <a href="/logout" class="btn btn--secondary btn--sm">Logout</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Layout wrapper -->
    <div class="app-layout">
        <!-- Sidebar: vendor list loaded via Datastar -->
        <aside class="sidebar">
            <h3 class="sidebar__title">Vendors</h3>
            <div id="vendor-list">
                <!-- Skeleton loading state -->
                <div class="loading-indicator active">
                    <div class="spinner"></div>
                    <span>Loading vendors...</span>
                </div>
            </div>
        </aside>

        <!-- Main content area -->
        <main class="main-content">
            <div id="toast-container" class="toast-container"></div>

            <div id="main-content-area">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>

    <?php if (!empty($isAdmin)): ?>
        <?php include __DIR__ . '/../Partial/admin-log-panel.php'; ?>
    <?php endif; ?>

</body>
</html>
