<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ReadInvoice</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="card__header">
                <div class="card__title">ReadInvoice</div>
                <div class="card__subtitle">Sign in to access the invoice dashboard</div>
            </div>
            <div class="card__body">
                <?php if (!empty($error)): ?>
                    <div class="toast toast--error" style="position:static; margin-bottom:1rem; min-width:auto;">
                        <div class="toast__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="toast__content">
                            <div class="toast__message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/login" data-on-submit>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="Enter your username"
                            required
                            autocomplete="username"
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn--primary btn--lg" style="width: 100%;">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
