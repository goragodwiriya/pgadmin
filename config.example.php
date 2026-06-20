<?php

declare(strict_types=1);

/**
 * คัดลอกไฟล์นี้เป็น config.local.php แล้วปรับค่าตามต้องการ
 * config.local.php จะถูก merge ทับค่าใน config.php
 */
return [
    'app_name' => 'PG Manager',
    'default_host' => 'localhost',
    'default_port' => 5432,
    'default_user' => 'postgres',
    'rows_per_page' => 50,
    'max_query_rows' => 1000,
    'session_timeout' => 1800,
    'login_rate_limit' => 5,
    'login_rate_window' => 300,
    'login_rate_block' => 900,
    'read_only' => false,
    'default_locale' => 'th',
];
