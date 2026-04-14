<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Already logged in
if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(sanitize($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login($email, $password)) {
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$timeout = !empty($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css"/>
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon">🤖</div>
            <h1><?= APP_NAME ?></h1>
            <p>Sign in to your dashboard</p>
        </div>

        <?php if ($timeout): ?>
        <div class="alert alert-warning">⏰ Your session expired. Please sign in again.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">✗ <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="admin@example.com"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                />
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    required
                />
            </div>

            <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:8px;">
                Sign In →
            </button>
        </form>

        <div style="margin-top:20px;text-align:center;font-size:12.5px;color:var(--text3)">
            Default: <code>admin@example.com</code> / <code>password</code>
        </div>
    </div>
</div>
</body>
</html>
