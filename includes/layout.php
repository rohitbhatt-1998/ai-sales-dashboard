<?php
/**
 * Layout helper — renders head, nav, and footer wrappers
 */

function renderHead(string $title = '', string $extraCss = ''): void {
    $pageTitle = $title ? h($title) . ' — ' . APP_NAME : APP_NAME;
    $user = Auth::user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css"/>
    <?= $extraCss ?>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="logo-icon">🤖</span>
                <span class="logo-text">AI Sales<br/><small>Calling Assistant</small></span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">☰</button>
        </div>

        <nav class="sidebar-nav">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF'], '.php');
            $navItems = [
                ['dashboard',   '📊', 'Dashboard',    'pages/dashboard.php'],
                ['leads',       '👥', 'Leads',        'pages/leads.php'],
                ['call-logs',   '📞', 'Call Logs',    'pages/call_logs.php'],
                ['ai-config',   '⚙️', 'AI Config',    'pages/ai_config.php'],
                ['queue',       '🔄', 'Call Queue',   'pages/queue.php'],
            ];
            foreach ($navItems as [$id, $icon, $label, $path]):
                $active = ($currentPage === $id || $currentPage === str_replace('-', '_', $id)) ? 'active' : '';
            ?>
            <a href="<?= BASE_URL ?>/<?= $path ?>" class="nav-item <?= $active ?>">
                <span class="nav-icon"><?= $icon ?></span>
                <span class="nav-label"><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= h($user['username']) ?></div>
                    <div class="user-role"><?= h($user['role']) ?></div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Logout">⏏</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
                <h1 class="page-title"><?= h($title) ?></h1>
            </div>
            <div class="topbar-right">
                <div class="queue-indicator" id="queueIndicator" title="Queue Status">
                    <span class="queue-dot"></span>
                    <span class="queue-count">Queue: <strong id="queueCount">—</strong></span>
                </div>
                <div class="topbar-time" id="topbarTime"></div>
            </div>
        </div>
        <div class="page-content">
    <?php
}

function renderFoot(string $extraJs = ''): void {
    ?>
        </div><!-- .page-content -->
    </main>
</div><!-- .app-wrapper -->

<!-- Global Toast Notification -->
<div class="toast-container" id="toastContainer"></div>

<!-- Global Modal -->
<div class="modal-overlay" id="modalOverlay" style="display:none">
    <div class="modal-box" id="modalBox">
        <div class="modal-header">
            <h3 id="modalTitle">—</h3>
            <button class="modal-close" id="modalClose">✕</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer" id="modalFooter"></div>
    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    const CSRF_TOKEN = '<?= csrfToken() ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<?= $extraJs ?>
</body>
</html>
    <?php
}
