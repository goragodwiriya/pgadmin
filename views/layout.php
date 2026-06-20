<!DOCTYPE html>
<html lang="<?php echo e(I18n::current()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle) ?> — <?php echo e(config('app_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function () {
            const theme = localStorage.getItem('pg_manager_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo">🐘</span>
                <div>
                    <strong><?php echo e(config('app_name')) ?></strong>
                    <small><?php echo e($credentials['host'] ?? '') ?>:<?php echo e((string) ($credentials['port'] ?? '')) ?></small>
                </div>
            </div>

            <div class="sidebar-db">
                <span class="label"><?php echo e(__('app.database')) ?></span>
                <a href="app.php?page=databases" class="db-link" title="เปลี่ยน Database">
                    <strong><?php echo e(Session::getCurrentDatabase()) ?></strong>
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="app.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon">📊</span> <?php echo e(__('nav.dashboard')) ?>
                </a>
                <a href="app.php?page=databases" class="<?php echo $page === 'databases' ? 'active' : '' ?>">
                    <span class="icon">🗄️</span> <?php echo e(__('nav.databases')) ?>
                </a>
                <a href="app.php?page=schemas" class="<?php echo $page === 'schemas' ? 'active' : '' ?>">
                    <span class="icon">📁</span> <?php echo e(__('nav.schemas')) ?>
                </a>
                <a href="app.php?page=tables" class="<?php echo in_array($page, ['tables', 'structure', 'data']) ? 'active' : '' ?>">
                    <span class="icon">📋</span> <?php echo e(__('nav.tables')) ?>
                </a>
                <a href="app.php?page=views" class="<?php echo $page === 'views' ? 'active' : '' ?>">
                    <span class="icon">👁️</span> <?php echo e(__('nav.views')) ?>
                </a>
                <a href="app.php?page=sequences" class="<?php echo $page === 'sequences' ? 'active' : '' ?>">
                    <span class="icon">🔢</span> <?php echo e(__('nav.sequences')) ?>
                </a>
                <a href="app.php?page=functions" class="<?php echo $page === 'functions' ? 'active' : '' ?>">
                    <span class="icon">⚙️</span> <?php echo e(__('nav.functions')) ?>
                </a>
                <div class="nav-divider"></div>
                <a href="app.php?page=sql" class="<?php echo $page === 'sql' ? 'active' : '' ?>">
                    <span class="icon">💻</span> <?php echo e(__('nav.sql')) ?>
                </a>
                <a href="app.php?page=import" class="<?php echo $page === 'import' ? 'active' : '' ?>">
                    <span class="icon">📥</span> <?php echo e(__('nav.import')) ?>
                </a>
                <a href="app.php?page=backup" class="<?php echo $page === 'backup' ? 'active' : '' ?>">
                    <span class="icon">💾</span> <?php echo e(__('nav.backup')) ?>
                </a>
                <a href="app.php?page=roles" class="<?php echo $page === 'roles' ? 'active' : '' ?>">
                    <span class="icon">👤</span> <?php echo e(__('nav.roles')) ?>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-title">
                    <h1><?php echo e($pageTitle) ?></h1>
                    <?php if (isReadOnly()): ?>
                    <span class="read-only-badge"><?php echo e(__('app.read_only')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="top-bar-actions">
                    <?php if (in_array($page, dbRequiredPages(), true)): ?>
                    <span class="db-badge">🗄️ <?php echo e(Session::getCurrentDatabase()) ?></span>
                    <?php endif; ?>
                    <span class="top-bar-user"><?php echo e($credentials['username'] ?? '') ?></span>
                    <button type="button" id="themeToggle" class="btn btn-outline btn-sm" title="Toggle theme">🌓</button>
                    <a href="app.php?page=logout" class="btn btn-outline btn-sm"><?php echo e(__('app.logout')) ?></a>
                </div>
            </header>

            <div class="page-body">
                <?php foreach (getFlash() as $msg): ?>
                    <div class="alert alert-<?php echo e($msg['type']) ?>"><?php echo $msg['message'] ?></div>
                <?php endforeach; ?>

                <?php
                    $viewFile = __DIR__.'/pages/'.$page.'.php';
                    if (file_exists($viewFile)) {
                        require $viewFile;
                    } else {
                        require __DIR__.'/pages/dashboard.php';
                    }
                ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
