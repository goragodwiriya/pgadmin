<?php

declare(strict_types=1);

$config = [
    'app_name' => 'PG Manager',
    'app_version' => '1.1.0',
    'session_name' => 'pg_manager_session',
    'default_host' => 'localhost',
    'default_port' => 5432,
    'default_user' => 'postgres',
    'rows_per_page' => 50,
    'max_query_rows' => 1000,
    'allowed_export_formats' => ['sql', 'csv', 'json'],
    'session_timeout' => 1800,
    'login_rate_limit' => 5,
    'login_rate_window' => 300,
    'login_rate_block' => 900,
    'read_only' => false,
    'default_locale' => 'th',
    'available_locales' => ['th', 'en'],
    'query_history_limit' => 20,
];

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    $config = array_merge($config, require $localConfig);
}

return $config;
