<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail('index.php');

    $maxAttempts = (int) config('login_rate_limit', 5);
    $window = (int) config('login_rate_window', 300);
    $block = (int) config('login_rate_block', 900);

    if (RateLimiter::isBlocked('login', $maxAttempts, $window)) {
        $seconds = RateLimiter::secondsUntilUnblock('login');
        $error = __('error.rate_limit', ['seconds' => (string) $seconds]);
    } else {
    $host = trim($_POST['host'] ?? config('default_host'));
    $port = (int) ($_POST['port'] ?? config('default_port'));
    $username = trim($_POST['username'] ?? config('default_user'));
    $password = $_POST['password'] ?? '';
    $database = trim($_POST['database'] ?? 'postgres');

    try {
        $db = new Database($host, $port, $username, $password, $database);
        $test = $db->testConnection();

        if (!$test['success']) {
            throw new RuntimeException($test['error']);
        }

        session_regenerate_id(true);
        Csrf::regenerate();

        Session::setCredentials([
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'database' => $database,
        ]);
        Session::touchActivity();
        RateLimiter::clear('login');

        redirect('app.php');
    } catch (Throwable $e) {
        RateLimiter::recordFailure('login', $maxAttempts, $window, $block);
        $error = $e->getMessage();
    }
    }
}

if (Session::isActive() && !isset($error)) {
    redirect('app.php');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app_name')) ?> — เชื่อมต่อ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">🐘</div>
                <h1><?= e(config('app_name')) ?></h1>
                <p>PostgreSQL Database Manager</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <?php csrfField(); ?>
                <div class="form-group">
                    <label for="host">Host</label>
                    <input type="text" id="host" name="host" value="<?= e($_POST['host'] ?? config('default_host')) ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="port">Port</label>
                        <input type="number" id="port" name="port" value="<?= e((string) ($_POST['port'] ?? config('default_port'))) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="database">Database</label>
                        <input type="text" id="database" name="database" value="<?= e($_POST['database'] ?? 'postgres') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? config('default_user')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block">เชื่อมต่อ</button>
            </form>
        </div>
        <p class="login-footer">v<?= e(config('app_version')) ?> — PHP PostgreSQL Manager</p>
    </div>
</body>
</html>
