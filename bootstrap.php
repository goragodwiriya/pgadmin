<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/classes/Csrf.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/PgManager.php';
require_once __DIR__ . '/classes/Session.php';
require_once __DIR__ . '/classes/RateLimiter.php';
require_once __DIR__ . '/classes/I18n.php';

initSecureSession();
I18n::init();

function initSecureSession(): void
{
    global $config;

    $isSecure = isHttpsRequest();

    session_name($config['session_name'] ?? 'pg_manager_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();

    Csrf::init();
}

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function config(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function __(string $key, array $replace = []): string
{
    return I18n::translate($key, $replace);
}

function isReadOnly(): bool
{
    return (bool) config('read_only', false);
}

function requireWritable(string $redirectUrl = 'app.php'): void
{
    if (isReadOnly()) {
        flash('error', __('error.read_only'));
        redirect($redirectUrl);
    }
}

function isReadOnlySql(string $sql): bool
{
    $trimmed = ltrim($sql);
    if ($trimmed === '') {
        return false;
    }

    if (preg_match('/^(SELECT|WITH|EXPLAIN|SHOW)\b/is', $trimmed)) {
        return true;
    }

    return !preg_match(
        '/\b(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|GRANT|REVOKE|COPY)\b/is',
        $trimmed
    );
}

function requireConnection(): Database
{
    if (!Session::isActive()) {
        flash('error', __('error.session_expired'));
        redirect('index.php');
    }

    Session::touchActivity();

    $db = Session::getConnection();
    if ($db === null) {
        redirect('index.php');
    }
    return $db;
}

function dbGoUrl(string $database, string $targetPage): string
{
    return 'app.php?page=databases&action=go&db=' . rawurlencode($database)
        . '&to=' . rawurlencode($targetPage);
}

/** @return list<string> */
function dbRequiredPages(): array
{
    return ['schemas', 'tables', 'views', 'sequences', 'functions', 'sql', 'import', 'structure', 'data'];
}

function renderColumnField(array $col, string $fieldName, mixed $value = null, bool $showNullOption = true): void
{
    require __DIR__ . '/views/partials/column_field.php';
}

function csrfField(): void
{
    echo '<input type="hidden" name="' . e(Csrf::fieldName()) . '" value="' . e(Csrf::token()) . '">';
}
